require 'kramdown-prismic'
require 'json'

print Kramdown::Document.new(ARGV[0], input: :markdown).to_prismic.to_json.to_str