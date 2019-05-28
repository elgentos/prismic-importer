<?php

namespace App\Commands;

use Cocur\Slugify\Slugify;
use Intervention\Image\ImageManagerStatic as Image;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;
use Webuni\FrontMatter\FrontMatter;

/**
 * Class BundleNewsitems
 * @package App\Commands
 */
class BundleNewsitems extends BaseBundle
{
    protected $GATSBY_SRC = '../gatsby/src/news/*.md';
    protected $GATSBY_STATIC_UPLOADS_DIR = '../gatsby/static/';

    protected $GATSBY_CONTENT_TYPE_ID = 'news';
    protected $PRISMIC_CONTENT_TYPE_ID = 'news';

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'bundle:newsitems {--export= : Optional Prismic Export file to map UIDs to IDs}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Bundle News NetlifyCMS markdown files into a Zip archive with Prismic JSON files';

    /**
     * @param $data
     * @param $filename
     * @return array
     */
    public function reformatIntoPrismicStructure($data)
    {
        $slugify = new Slugify();
        $uid = $slugify->slugify($data['title']);

        $prismic = [
            'type' => $this->PRISMIC_CONTENT_TYPE_ID,
            'uid' => $uid
        ];

        $textFields = ['title', 'author', 'excerpt'];
        $markdownContentFields = ['content'];
        $externalLinkFields = ['youtube'];
        $mediaFields = ['photo'];
        $dateFields = ['date'];

        return parent::reformatFieldsIntoPrismicStructure(
            $prismic,
            $data,
            $textFields,
            $markdownContentFields,
            $externalLinkFields,
            $mediaFields,
            $dateFields
        );
    }
}
