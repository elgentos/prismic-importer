# Prismic Importer

This little Laravel Zero command line tool can transform Gatsby sourced Markdown files into a Prismic JSON structure.

It contains a few commands in the `bundle:` namespace to take Markdown source files for a custom type to Prismic JSON files and bundles them into a ZIP.

It can;

- Automatically generate the ZIP file to upload in Prismics Import/Export tool;
- Encode images to JPG with 90% quality to cut down on filesize;
- Update existing documents;
- Add new documents.

## Supported custom types
- Author
- Newsitem

## Create your own bundle command for your custom type
To create a bundle command for your custom type, create a new command in `app/Commands` and extend the `BaseBundle` class. You will need to define the following variables;

```
$GATSBY_SRC - the relative path to the markdown files with a glob pattern (i.e. '../../gatsby/src/authors/*.md')
$GATSBY_CONTENT_TYPE_ID - the type name as used in Gatsby
$PRISMIC_CONTENT_TYPE_ID - the API ID for the custom type as used in Prismic
```

Then you need to implement the `reformatIntoPrismicStructure($data)` method. This will reformat the PHP array version of the Markdown file into the format Prismic needs. Please look at the already existing bundle commands for examples. 

## Requirements
- PHP 7.2
- Ruby with the `kramdown-prismic` gem (to be able to convert Markdown rich text to Prismic's JSON structure)

## Usage

Run it like this;

```php
php prismic-importer bundle:authors
```

If you would like to update existing documents, first export all published documents from Prismic. Then refer to the exported ZIP file in the `export` option;

```php
php prismic-importer bundle:authors --export 28-05-2019\#11-54-22_repositoryname.zip
```

This will map the generated UID's in the bundle command to existing internal Prismic ID's and will generate a filename with that ID so Prismic knows it should update it.