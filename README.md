# Prismic Importer

This little Laravel Zero command line tool can transform Gatsby sourced Markdown files into a Prismic JSON structure.

It contains a `bundle:authors` command to take Markdown source files for the Author custom type to Prismic JSON files and bundles them into a ZIP.

It can;

- Automatically generate the ZIP file to upload in Prismics Import/Export tool;
- Encode images to JPG with 90% quality to cut down on filesize;
- Update existing documents;
- Add new documents.

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