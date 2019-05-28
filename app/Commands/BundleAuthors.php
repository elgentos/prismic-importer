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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();
    }


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

}
