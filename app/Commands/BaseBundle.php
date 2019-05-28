<?php

namespace App\Commands;

use Intervention\Image\ImageManagerStatic as Image;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;
use Webuni\FrontMatter\FrontMatter;

/**
 * Class BundleNewsitems
 * @package App\Commands
 */
abstract class BaseBundle extends Command
{
    protected $UPLOAD_ZIP_FILENAME = '';
    protected $GATSBY_SRC = '';

    protected $GATSBY_STATIC_UPLOADS_DIR = '../gatsby/static/';

    protected $IMAGE_ENCODING_FORMAT = 'jpg';
    protected $IMAGE_ENCODING_QUALITY = 90;
    protected $PRISMIC_FILESIZE_UPLOAD_LIMIT_IN_MB = 100;

    protected $GATSBY_CONTENT_TYPE_ID = '';
    protected $PRISMIC_CONTENT_TYPE_ID = '';

    /**
     * Contains mapping from UID ('path') to Prismic ID if --export is given
     *
     * @var array
     */
    protected $mapping = [];

    /**
     * @param $data
     * @param $filename
     * @return array
     */
    abstract function reformatIntoPrismicStructure($data);


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
        if (file_exists($this->UPLOAD_ZIP_FILENAME)) {
            unlink($this->UPLOAD_ZIP_FILENAME);
        }

        // Create Zip Archive
        $zip = new ZipArchive;
        $zip->open($this->UPLOAD_ZIP_FILENAME, ZipArchive::CREATE);

        // Read markdown files from Gatsby dir
        $files = glob($this->GATSBY_SRC);
        foreach ($files as $file) {
            $markdown = file_get_contents($file);

            // Transform Markdown frontmatter into JSON files
            $array = $this->parseMarkdownToArray($markdown);

            $this->output->writeln('Processing ' . $this->PRISMIC_CONTENT_TYPE_ID . ' ' . $array['title']);

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

        if (filesize($this->UPLOAD_ZIP_FILENAME) > $this->PRISMIC_FILESIZE_UPLOAD_LIMIT_IN_MB * 1024 * 1024) {
            $this->output->writeln('Warning: your ZIP filesize exceeds '  . $this->PRISMIC_FILESIZE_UPLOAD_LIMIT_IN_MB . 'mb, which is the maximum allowed to upload to Prismic. Try to reduce image quality or image dimensions.');
        }

        $this->output->writeln('Wrote to ' . $this->UPLOAD_ZIP_FILENAME);
    }

    /**
     * @param $markdown
     * @return array
     */
    protected function parseMarkdownToArray($markdown)
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
    protected function mapIdsToUids()
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
            if ($data['type'] === $this->GATSBY_CONTENT_TYPE_ID && isset($data['uid'])) {
                $id = $this->getIdFromFilename($filename);
                $this->mapping[$data['uid']] = $id;
            }
        }
    }

    /**
     * @param $filename
     * @return mixed
     */
    protected function getIdFromFilename($filename)
    {
        list($id,) = explode('$', basename($filename));
        return $id;
    }

    /**
     * @param $contentField
     * @return string|null
     */
    protected function getPrismicRichTextStructureFromMarkdown($contentField)
    {
        return json_decode(shell_exec('ruby kramdown-to-prismic.rb ' . escapeshellarg($contentField)), true);
    }

    /**
     * @param array $array
     * @param ZipArchive $zip
     * @return array
     */
    protected function addPhotoToZip(array $array, ZipArchive $zip): array
    {
        if (isset($array['photo']) && isset($array['photo']['url'])) {
            $photo = $this->GATSBY_STATIC_UPLOADS_DIR . $array['photo']['url'];
            $img = Image::make($photo)->encode($this->IMAGE_ENCODING_FORMAT, $this->IMAGE_ENCODING_QUALITY);
            $zip->addFromString('uploads/' . basename($photo), $img);
        }
        return $array;
    }

    /**
     * @param $file
     * @return mixed|string
     */
    protected function getFilenameInZip($file)
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
