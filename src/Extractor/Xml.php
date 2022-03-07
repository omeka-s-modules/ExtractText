<?php declare(strict_types=1);

namespace ExtractText\Extractor;

use DOMDocument;

/**
 * Use php dom to extract text.
 *
 * @see https://www.php.net/manual/fr/book.dom.php
 */
class Xml implements ExtractorInterface
{
    public function isAvailable()
    {
        return true;
    }

    public function extract($filePath, array $options = [])
    {
        try {
            $domXml = new DOMDocument();
            $domXml = $this->load($filePath);
        } catch (\Exception $e) {
            return false;
        }
        return $domXml->documentElement->textContent;
    }
}
