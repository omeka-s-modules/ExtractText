<?php
namespace ExtractText;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\Entity\Value;
use Omeka\File\Store\Local;
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
        /**
         * Before ingesting a media file, extract its text and set it to the
         * media. This will only happen when creating the media.
         */
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
        /**
         * After hydrating an item, aggregate its media's text and set it to the
         * item. This happens when creating and updating the item. Refreshes the
         * media's text first if the "extract_text_refresh" flag is passed in
         * the request.
         */
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.hydrate.post',
            function (Event $event) {
                $item = $event->getParam('entity');
                $data = $event->getParam('request')->getContent();
                $refreshText = (isset($data['extract_text_refresh']) && $data['extract_text_refresh']);
                $this->setTextToItem($item, $refreshText);
            }
        );
        /**
         * Add the "Extract text" checkbox to the resource batch update form.
         */
        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_elements',
            function (Event $event) {
                $form = $event->getTarget();
                $form->add([
                    'name' => 'extract_text_refresh',
                    'type' => 'Zend\Form\Element\Checkbox',
                    'options' => [
                        'label' => 'Extract text', // @translate
                    ],
                    'attributes' => [
                        'data-collection-action' => 'replace',
                    ],
                ]);
            }
        );
        /**
         * When preprocessing the batch update data, authorize the "extract_text
         * _refresh" flag. This will signal the process to refresh the text
         * while updating each item in the batch.
         */
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.preprocess_batch_update',
            function (Event $event) {
                $adapter = $event->getTarget();
                $data = $event->getParam('data');
                $rawData = $event->getParam('request')->getContent();
                if (isset($rawData['extract_text_refresh'])) {
                    $data['extract_text_refresh'] = (bool) $rawData['extract_text_batch'];
                }
                $event->setParam('data', $data);
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
        if (!@is_file($filePath)) {
            // The file doesn't exist.
            return false;
        }
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
     * @param Item $item
     * @param bool $refreshText
     */
    public function setTextToItem(Item $item, $refreshText = false)
    {
        $textProperty = $this->getTextProperty();
        if (false === $textProperty) {
            // The text property doesn't exist.
            return;
        }
        $store = $this->getServiceLocator()->get('Omeka\File\Store');
        $itemTexts = [];
        $itemMedia = $item->getMedia();
        // Order by position in case the position was changed on this request.
        $criteria = Criteria::create()->orderBy(['position' => Criteria::ASC]);
        foreach ($itemMedia->matching($criteria) as $media) {
            // Files must be stored locally to refresh extracted text.
            if ($refreshText && ($store instanceof Local)) {
                $filePath = $store->getLocalPath(sprintf('original/%s', $media->getFilename()));
                $this->setTextToMedia($filePath, $media);
            }
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
