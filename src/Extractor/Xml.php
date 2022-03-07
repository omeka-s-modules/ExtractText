<?php declare(strict_types=1);

namespace ExtractText\Extractor;

use DOMDocument;
use Laminas\Log\Logger;
use Omeka\Stdlib\Message;

/**
 * Use php standard extensions Xml/XmlReader to extract text.
 *
 * @see https://www.php.net/manual/fr/book.dom.php
 * @see https://www.php.net/manual/fr/book.xmlreader.php
 */
class Xml implements ExtractorInterface
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function isAvailable()
    {
        // The extensions are enabled by default, but a check is done.
        return extension_loaded('xml')
            && extension_loaded('xmlreader');
    }

    public function extract($filePath, array $options = [])
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $filePath = (string) $filePath;
        if (!$this->isWellFormedXml($filePath)) {
            return false;
        }

        try {
            $domXml = new DOMDocument();
            $domXml = $this->load($filePath);
        } catch (\Exception $e) {
            $message = new Message(
                'The file "%s" is not a valid xml or its encoding is different from the one set in the xml root tag.', // @translate
                basename($filePath)
            );
            $this->logger->err($message);
            $this->logger->err($e);
            return false;
        }

        return $domXml->documentElement->textContent;
    }

    /**
     * Check if an xml is well formed and log issues.
     *
     * @link https://stackoverflow.com/questions/13858074/validating-a-large-xml-file-400mb-in-php#answer-13858478
     */
    protected function isWellFormedXml(string $filePath): bool
    {
        // Use xmlReader.
        $xml_parser = xml_parser_create();
        if (!($fp = fopen($filePath, 'r'))) {
            $message = new Message(
                'File "%s" is not readable.', // @translate
                basename($filePath)
            );
            $this->logger->err($message);
            return false;
        }

        $errors = [];
        while ($data = fread($fp, 4096)) {
            if (!xml_parse($xml_parser, $data, feof($fp))) {
                $errors[] = sprintf('Line #%s: %s',
                    xml_get_current_line_number($xml_parser),
                    xml_error_string(xml_get_error_code($xml_parser))
                );
            }
        }
        xml_parser_free($xml_parser);

        if (count($errors)) {
            $message = new Message(
                'The file "%s" is not a valid xml or its encoding is different from the one set in the xml root tag: %s', // @translate
                basename($filePath), array_unique($errors)
            );
            $this->logger->err($message);
            return false;
        }

        return true;
    }
}
