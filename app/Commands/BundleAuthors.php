<?php

namespace App\Commands;

use Cocur\Slugify\Slugify;
use LaravelZero\Framework\Commands\Command;
use Michelf\Markdown;
use ZipArchive;
use Webuni\FrontMatter\FrontMatter;

class BundleAuthors extends Command
{
    const AUTHORS_UPLOAD_ZIP = 'authors_upload.zip';
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'bundle:authors {--export= : Prismic Export file}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Bundle Author NetlifyCMS markdown files into a Zip archive with Prismic JSON files';

    /**
     * Contains mapping from UID ('path') to Prismic ID
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

        // Copy images from Gatsby installation

        // Resize images (so the ultimate Zip file isn't above 100mb)

        // Read markdown files from Gatsby dir
        $files = glob('../gatsby/src/authors/*.md');
        $files = ['../gatsby/src/authors/emma-curvers.md'];
        foreach ($files as $file) {
            $markdown = file_get_contents($file);

            // Transform Markdown frontmatter into JSON files
            $array = $this->parseMarkdownToArray($markdown);

            // Reformat JSON files to match Prismic structure
            $json = $this->reformatIntoPrismicJson($array);

            // Zip it up!
            $uid = pathinfo($file)['filename'];
            if (isset($this->mapping[$uid])) {
                $filenameInZip = $this->mapping[$uid]; // filename should be the ID for update
            } else {
                $filenameInZip = 'new_' . pathinfo($file)['filename']; // or should be prepended with new_ for new
            }
            $filenameInZip .= '.json';
            $zip->addFromString($filenameInZip, $json);
        }

        // Close Zip
        $zip->close();

        $this->output->writeln('Wrote to ' . self::AUTHORS_UPLOAD_ZIP);
    }


    /**
     * @param $data
     * @param $filename
     * @return false|string
     */
    private function reformatIntoPrismicJson($data)
    {
        $updatePhoto = false;

        $slugify = new Slugify();
        $uid = $slugify->slugify(implode(' ', [$data['firstname'], $data['prefix'], $data['lastname']]));

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

        if (isset($data['photo']) && $updatePhoto) {
            $relativePhotoPath = substr($data['photo'], 1);
            $prismic['photo'] = [
                'origin' => ['url' => $relativePhotoPath],
                'url' => $relativePhotoPath
            ];
        }

        return json_encode($prismic);
    }

    /**
     *
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
     * @return array
     */
    private function mapIdsToUids()
    {
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
}
