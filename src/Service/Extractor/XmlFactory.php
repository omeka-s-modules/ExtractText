<?php declare(strict_types=1);

namespace ExtractText\Service\Extractor;

use ExtractText\Extractor\Xml;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class XmlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Xml(
            $services->get('Omeka\Logger')
        );
    }
}
