<?php

namespace ExtractText\Form;

use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => \Laminas\Form\Element\Radio::class,
            'name' => 'extracttext_default_is_public',
            'options' => [
                'label' => 'Default visibility', // @translate
                'info' => 'When a resource has no "extracted text" properties, this setting determines the visibility of created properties. Otherwise, this is determined by the visibility of the last "extracted text" property.', // @translate
                'value_options' => [
                    '0' => 'Private', // @translate
                    '1' => 'Public', // @translate
                ],
            ],
        ]);
    }
}
