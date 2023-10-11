<?php
namespace ExtractText\Job;

use Omeka\Entity;
use Omeka\Job\AbstractJob;

class SetTextToItem extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $module = $services->get('ModuleManager')->getModule('ExtractText');

        $itemId = $this->getArg('item_id');
        $textPropertyId = $this->getArg('text_property_id');
        $action = $this->getArg('action');

        $item = $entityManager->find(Entity\Item::class, $itemId);
        $textProperty = $entityManager->find(Entity\Property::class, $textPropertyId);

        $module->setTextToItem($item, $textProperty, $action);
        $entityManager->flush();
    }
}
