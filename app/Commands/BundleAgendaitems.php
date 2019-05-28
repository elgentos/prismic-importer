<?php

namespace App\Commands;

use Cocur\Slugify\Slugify;
use Intervention\Image\ImageManagerStatic as Image;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;
use Webuni\FrontMatter\FrontMatter;

/**
 * Class BundleAgendaitems
 * @package App\Commands
 */
class BundleAgendaitems extends BaseBundle
{
    protected $GATSBY_SRC = '../gatsby/src/agenda/*.md';
    protected $GATSBY_STATIC_UPLOADS_DIR = '../gatsby/static/';

    protected $GATSBY_CONTENT_TYPE_ID = 'agenda';
    protected $PRISMIC_CONTENT_TYPE_ID = 'agenda';

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'bundle:agendaitems {--export= : Optional Prismic Export file to map UIDs to IDs}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Bundle Agenda NetlifyCMS markdown files into a Zip archive with Prismic JSON files';

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

        $textFields = ['title', 'location'];
        $markdownContentFields = ['content'];
        $externalLinkFields = [];
        $mediaFields = ['photo'];
        $dateFields = [];
        $datetimeFields = ['datetime'];

        return parent::reformatFieldsIntoPrismicStructure(
            $prismic,
            $data,
            $textFields,
            $markdownContentFields,
            $externalLinkFields,
            $mediaFields,
            $dateFields,
            $datetimeFields
        );
    }
}
