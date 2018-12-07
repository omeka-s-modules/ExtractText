<?php
namespace ExtractText;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\Entity\Value;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    /**
     * Text property cache
     *
     * @var Omeka\Entity\Property|false
     */
    protected $textProperty;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        // Import the ExtractText vocabulary if it doesn't already exist.
        $api = $services->get('Omeka\ApiManager');
        $response = $api->search('vocabularies', [
            'namespace_uri' => 'http://omeka.org/s/vocabs/o-module-extracttext#',
            'limit' => 0,
        ]);
        if (0 === $response->getTotalResults()) {
            $importer = $services->get('Omeka\RdfImporter');
            $importer->import(
                'file',
                [
                    'o:namespace_uri' => 'http://omeka.org/s/vocabs/o-module-extracttext#',
                    'o:prefix' => 'extracttext',
                    'o:label' => 'Extract Text',
                    'o:comment' =>  null,
                ],
                [
                    'file' => __DIR__ . '/vocabs/extracttext.n3',
                    'format' => 'turtle',
                ]
            );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add text to media before ingesting the media file.
        $sharedEventManager->attach(
            '*',
            'media.ingest_file.pre',
            function (Event $event) {
                $tempFile = $event->getParam('tempFile');
                $this->setTextToMedia(
                    $tempFile->getTempPath(),
                    $event->getTarget(),
                    $tempFile->getMediaType()
                );
            }
        );
        // Add aggregated text to an item after hydrating the item.
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.hydrate.post',
            function (Event $event) {
                $this->setTextToItem($event->getParam('entity'));
            }
        );
    }

    /**
     * Extract text from a file and set it to a media.
     *
     * @param string $filePath
     * @param Media $media
     * @param string $mediaType
     * @return null|false
     */
    public function setTextToMedia($filePath, Media $media, $mediaType = null)
    {
        $textProperty = $this->getTextProperty();
        if (false === $textProperty) {
            // The text property doesn't exist.
            return false;
        }
        if (null === $mediaType) {
            // Fall back on the media type set to the media.
            $mediaType = $media->getMediaType();
        }
        $text = $this->extractText($filePath, $mediaType);
        if (false === $text) {
            // Could not extract text from the file.
            return false;
        }
        $this->setTextPropertyValue($media, $text);
    }

    /**
     * Aggregate text from child media and set it to their parent item.
     *
     * @param string $filePath
     * @param Media $media
     * @param string $mediaType
     * @return null|false
     */
    public function setTextToItem(Item $item)
    {
        $textProperty = $this->getTextProperty();
        if (false === $textProperty) {
            // The text property doesn't exist.
            return;
        }
        $itemTexts = [];
        $itemMedia = $item->getMedia();
        // Order by position in case the position was changed on this request.
        $criteria = Criteria::create()->orderBy(['position' => Criteria::ASC]);
        foreach ($itemMedia->matching($criteria) as $media) {
            $mediaValues = $media->getValues();
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('property', $this->textProperty))
                ->andWhere(Criteria::expr()->eq('type', 'literal'));
            foreach($mediaValues->matching($criteria) as $mediaValueTextProperty) {
                $itemTexts[] = $mediaValueTextProperty->getValue();
            }
        }
        $itemText = trim(implode(PHP_EOL, $itemTexts));
        $this->setTextPropertyValue($item, ('' === $itemText) ? null : $itemText);
    }

    /**
     * Get the text property, caching on first pass.
     *
     * @return Omeka\Entity\Property|false
     */
    public function getTextProperty()
    {
        if (isset($this->textProperty)) {
            return $this->textProperty;
        }
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $textProperty = $entityManager->createQuery('
            SELECT p FROM Omeka\Entity\Property p
            JOIN p.vocabulary v
            WHERE p.localName = :localName
            AND v.namespaceUri = :namespaceUri
        ')->setParameters([
            'localName' => 'extracted_text',
            'namespaceUri' => 'http://omeka.org/s/vocabs/o-module-extracttext#',
        ])->getOneOrNullResult();
        $this->textProperty = (null === $textProperty) ? false : $textProperty;
        return $this->textProperty;
    }

    /**
     * Extract text from a file.
     *
     * @param string $filePath
     * @param string $mediaType
     * @param array $options
     * @return string|false
     */
    public function extractText($filePath, $mediaType = null, array $options = [])
    {
        if (null === $mediaType) {
            // Fall back on PHP's magic.mime file.
            $mediaType = mime_content_type($filePath);
        }
        $extractors = $this->getServiceLocator()->get('ExtractText\ExtractorManager');
        try {
            $extractor = $extractors->get($mediaType);
        } catch (ServiceNotFoundException $e) {
            // No extractor assigned to the media type.
            return false;
        }
        // Extractors should return false if they cannot extract text.
        return $extractor->extract($filePath, $options);
    }

    /**
     * Set text as a text property value of a resource.
     *
     * Clears all existing text property values from the resource before setting
     * the value. Pass anything but a string to $text to just clear the values.
     *
     * @param Resource $resource
     * @param string $text
     */
    public function setTextPropertyValue(Resource $resource, $text)
    {
        $textProperty = $this->getTextProperty();
        if (false === $textProperty) {
            // The text property doesn't exist.
            return;
        }
        $resourceValues = $resource->getValues();
        // Clear values.
        $criteria = Criteria::create()->where(
            Criteria::expr()->eq('property', $textProperty)
        );
        foreach ($resourceValues->matching($criteria) as $resourceValueTextProperty) {
            $resourceValues->removeElement($resourceValueTextProperty);
        }
        // Create and add the value.
        if (is_string($text)) {
            $value = new Value;
            $value->setResource($resource);
            $value->setType('literal');
            $value->setProperty($textProperty);
            $value->setValue($text);
            $resourceValues->add($value);
        }
    }
}
