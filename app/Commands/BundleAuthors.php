<?php

namespace App\Commands;

use Cocur\Slugify\Slugify;
use Intervention\Image\ImageManagerStatic as Image;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;
use Webuni\FrontMatter\FrontMatter;

/**
 * Class BundleAuthors
 * @package App\Commands
 */
class BundleAuthors extends Command
{
    const AUTHORS_UPLOAD_ZIP = 'authors_upload.zip';
    const GATSBY_SRC_AUTHORS = '../gatsby/src/authors/*.md';
    const GATSBY_STATIC_UPLOADS_DIR = '../gatsby/static/';

    const IMAGE_ENCODING_FORMAT = 'jpg';
    const IMAGE_ENCODING_QUALITY = 90;
    const PRISMIC_FILESIZE_UPLOAD_LIMIT_IN_MB = 100;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'bundle:authors {--export= : Optional Prismic Export file to map UIDs to IDs}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Bundle Author NetlifyCMS markdown files into a Zip archive with Prismic JSON files';

    /**
     * Contains mapping from UID ('path') to Prismic ID if --export is given
     *
     * @var array
     */
    protected $mapping = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Create ID to UID mapping
        $this->mapIdsToUids();

        // Remove old Zip if it exists
        if (file_exists(self::AUTHORS_UPLOAD_ZIP)) {
            unlink(self::AUTHORS_UPLOAD_ZIP);
        }

        // Create Zip Archive
        $zip = new ZipArchive;
        $zip->open(self::AUTHORS_UPLOAD_ZIP, ZipArchive::CREATE);

        // Read markdown files from Gatsby dir
        $files = glob(self::GATSBY_SRC_AUTHORS);
        foreach ($files as $file) {
            $markdown = file_get_contents($file);

            // Transform Markdown frontmatter into JSON files
            $array = $this->parseMarkdownToArray($markdown);

            $this->output->writeln('Processing author ' . $array['title']);

            // Reformat JSON files to match Prismic structure
            $array = $this->reformatIntoPrismicStructure($array);
            
            // Process photo
            $array = $this->addPhotoToZip($array, $zip);
            
            // Encode to JSON
            $json = json_encode($array);

            // Get filename (new document or update document)
            $filenameInZip = $this->getFilenameInZip($file);

            // Zip it up!
            $zip->addFromString($filenameInZip, $json);
        }

        // Close Zip
        $zip->close();

        if (filesize(self::AUTHORS_UPLOAD_ZIP) > self::PRISMIC_FILESIZE_UPLOAD_LIMIT_IN_MB * 1024 * 1024) {
            $this->output->writeln('Warning: your ZIP filesize exceeds '  . self::PRISMIC_FILESIZE_UPLOAD_LIMIT_IN_MB . 'mb, which is the maximum allowed to upload to Prismic. Try to reduce image quality or image dimensions.');
        }

        $this->output->writeln('Wrote to ' . self::AUTHORS_UPLOAD_ZIP);
    }


    /**
     * @param $data
     * @param $filename
     * @return array
     */
    private function reformatIntoPrismicStructure($data)
    {
        $slugify = new Slugify();
        $uid = $slugify->slugify(implode(' ', [$data['firstname'], $data['prefix'] ?? null, $data['lastname']]));

        $prismic = [
            'type' => 'authors',
            'uid' => $uid
        ];

        $markdownContentFields = ['biography'];
        foreach ($markdownContentFields as $contentField) {
            $prismic[$contentField] = $this->getPrismicRichTextStructureFromMarkdown($data[$contentField]);
        }

        foreach (['firstname', 'lastname', 'prefix', 'title'] as $textField) {
            if (isset($data[$textField])) {
                $prismic[$textField] = $data[$textField];
            }
        }

        foreach (['instagram', 'twitter', 'facebook', 'website'] as $externalLink) {
            if (isset($data[$externalLink]) && $data[$externalLink]) {
                $prismic[$externalLink] = [];
                $prismic[$externalLink][] = [
                    'preview' => null,
                    'target' => '_blank',
                    'url' => $data[$externalLink]
                ];
            }
        }

        $mediaFields = ['photo'];
        foreach ($mediaFields as $mediaField) {
            if (isset($data[$mediaField])) {
                $relativeMediaField = substr($data[$mediaField], 1);
                $prismic[$mediaField] = [
                    'origin' => ['url' => $relativeMediaField],
                    'url' => $relativeMediaField
                ];
            }
        }

        return $prismic;
    }

    /**
     * @param $markdown
     * @return array
     */
    private function parseMarkdownToArray($markdown)
    {
        $frontMatter = new FrontMatter();

        $document = $frontMatter->parse($markdown);

        $data = $document->getData();
        $data['body'] = $document->getContent();

        return $data;
    }

    /**
     * @return void
     */
    private function mapIdsToUids()
    {
        if (!$this->option('export')) {
            return;
        }

        $zip = new ZipArchive();
        $zip->open($this->option('export'));
        for( $i = 0; $i < $zip->numFiles; $i++ ){
            $stat = $zip->statIndex( $i );
            $filename = $stat['name'];
            $json = $zip->getFromName($filename);
            $data = json_decode($json, true);
            if ($data['type'] === 'authors' && isset($data['uid'])) {
                $id = $this->getIdFromFilename($filename);
                $this->mapping[$data['uid']] = $id;
            }
        }
    }

    /**
     * @param $filename
     * @return mixed
     */
    private function getIdFromFilename($filename)
    {
        list($id,) = explode('$', basename($filename));
        return $id;
    }

    /**
     * @param $contentField
     * @return string|null
     */
    private function getPrismicRichTextStructureFromMarkdown($contentField)
    {
        return json_decode(shell_exec('ruby kramdown-to-prismic.rb ' . escapeshellarg($contentField)), true);
    }

    /**
     * @param array $array
     * @param ZipArchive $zip
     * @return array
     */
    private function addPhotoToZip(array $array, ZipArchive $zip): array
    {
        if (isset($array['photo']) && isset($array['photo']['url'])) {
            $photo = self::GATSBY_STATIC_UPLOADS_DIR . $array['photo']['url'];
            $img = Image::make($photo)->encode(self::IMAGE_ENCODING_FORMAT, self::IMAGE_ENCODING_QUALITY);
            $zip->addFromString('uploads/' . basename($photo), $img);
        }
        return $array;
    }

    /**
     * @param $file
     * @return mixed|string
     */
    private function getFilenameInZip($file)
    {
        $uid = pathinfo($file)['filename'];
        if (isset($this->mapping[$uid])) {
            $filenameInZip = $this->mapping[$uid]; // filename should be the ID for update
        } else {
            $filenameInZip = 'new_' . pathinfo($file)['filename']; // or should be prepended with new_ for new
        }
        $filenameInZip .= '.json';
        return $filenameInZip;
    }
}
