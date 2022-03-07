<?php declare(strict_types=1);

namespace ExtractText\Extractor;

use DOMDocument;
use Laminas\Log\Logger;
use Omeka\Stdlib\Message;
use XMLReader;
use XSLTProcessor;

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

        // TODO The language is extractable, but not managed by the module.

        $mediaType = $this->getMediaTypeXml($filePath);
        $mainType = str_replace(['application/', 'text/', '+', 'xml', 'vnd.'], ['', '', '', '', ''], $mediaType);
        $xslPath = dirname(__DIR__, 2) . '/data/extractors/xml-' . $mainType . '.xslt';
        $text = file_exists($xslPath) && filesize($xslPath) && is_readable($filePath)
            ? $this->extractViaXsl($filePath, $xslPath, $options)
            : $this->extractViaDom($filePath, $options);
        return is_null($text) ? false : $text;
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

    /**
     * Extract a more precise xml media type when possible.
     *
     * @see https://github.com/omeka/omeka-s/pull/1464/files
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-XmlViewer/-/blob/master/data/media-types/media-type-identifiers.php
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-XmlViewer/-/blob/master/src/File/TempFile.php
     */
    protected function getMediaTypeXml(string $filePath): ?string
    {
        libxml_clear_errors();

        $reader = new XMLReader();
        if (!$reader->open($filePath)) {
            $message = new Message(
                'File "%s" is not readable.', // @translate
                basename($filePath)
            );
            $this->logger->err($message);
            return null;
        }

        $type = null;

        // Don't output error in case of a badly formatted file since there is no logger.
        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::DOC_TYPE) {
                $type = $reader->name;
                break;
            }

            // To be improved or skipped.
            if ($reader->nodeType === XMLReader::PI
                && !in_array($reader->name, [
                    'xml-model',
                    'xml-stylesheet',
                    'oxygen',
                ])
            ) {
                $matches = [];
                if (preg_match('~href="(.+?)"~mi', $reader->value, $matches)) {
                    $type = $matches[1];
                    break;
                }
            }

            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->namespaceURI === 'urn:oasis:names:tc:opendocument:xmlns:office:1.0') {
                    $type = $reader->getAttributeNs('mimetype', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
                } else {
                    $type = $reader->namespaceURI ?: $reader->getAttribute('xmlns');
                }
                if (!$type) {
                    $type = $reader->name;
                }
                break;
            }
        }

        $reader->close();

        $error = libxml_get_last_error();
        if ($error) {
            $message = new Message(
                'Error level %s, code %s, for file "%s", line %s, column %s: %s', // @translate
                $error->level, $error->code, $error->file, $error->line, $error->column, $error->message
            );
            $this->logger->err($message);
        }

        $mediaTypeIdentifiers = require dirname(__DIR__, 2) . '/data/media-types/media-type-identifiers.php';
        return $mediaTypeIdentifiers[$type] ?? null;
    }

    /**
     * @param string $filePath The xml file should be checked for well-formed.
     */
    protected function extractViaDom(string $filePath, array $options): ?string
    {
        $domXml = $this->domXmlLoad($filePath, $options);
        return $domXml ? null : $domXml->documentElement->textContent;
    }

    protected function extractViaXsl(string $filePath, string $xslPath, array $options): ?string
    {
        $domXml = $this->domXmlLoad($filePath, $options);
        $domXsl = $this->domXmlLoad($xslPath, $options);
        if (!$domXml || !$domXsl) {
            return null;
        }

        $proc = new XSLTProcessor();
        $proc->importStyleSheet($domXsl);
        $proc->setParameter('', $options);
        $result = $proc->transformToXml($domXml);
        return $result === false ? null : $result;
    }

    /**
     * Load a xml or xslt file into a Dom document via file system or http.
     *
     * @param string $filepath Path of xml file on file system or via http.
     * @return \DomDocument
     * @throws \Exception
     */
    protected function domXmlLoad(string $filePath, array $options): ?DOMDocument
    {
        try {
            $domXml = new DOMDocument();
            @$domXml->load($filePath);
        } catch (\Exception $e) {
            $message = new Message(
                'The file "%s" is not a valid xml or its encoding is different from the one set in the xml root tag: %s.', // @translate
                basename($filePath), $e
            );
            $this->logger->err($message);
            return null;
        }
        return $domXml;
    }
}
