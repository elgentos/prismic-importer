<?php

namespace App\Commands;

use Cocur\Slugify\Slugify;

/**
 * Class BundleAuthors
 * @package App\Commands
 */
class BundleAuthors extends BaseBundle
{
    protected $GATSBY_SRC = '../gatsby/src/authors/*.md';

    protected $GATSBY_CONTENT_TYPE_ID = 'authors';
    protected $PRISMIC_CONTENT_TYPE_ID = 'authors';

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
     * @param $data
     * @param $filename
     * @return array
     */
    public function reformatIntoPrismicStructure($data)
    {
        $slugify = new Slugify();
        $uid = $slugify->slugify(implode(' ', [$data['firstname'], $data['prefix'] ?? null, $data['lastname']]));

        $prismic = [
            'type' => $this->PRISMIC_CONTENT_TYPE_ID,
            'uid' => $uid
        ];

        $textFields = ['firstname', 'lastname', 'prefix', 'title'];
        $externalLinkFields = ['instagram', 'twitter', 'facebook', 'website'];
        $markdownContentFields = ['biography'];
        $mediaFields = ['photo'];

        return parent::reformatFieldsIntoPrismicStructure(
            $prismic,
            $data,
            $textFields,
            $markdownContentFields,
            $externalLinkFields,
            $mediaFields
        );
    }

}
