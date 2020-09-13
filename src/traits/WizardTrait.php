<?php
/**
 * Wizarf trait
 */
namespace buttflattery\formwizard\traits;

use yii\helpers\ArrayHelper;
use yii\base\InvalidArgumentException as ArgException;
use yii\web\JsExpression;
use yii\helpers\Html;

trait WizardTrait
{
    /**
     * Sorts the fields. If the `fieldOrder` option is specified then the
     * order will be dependend on the order specified in the `fieldOrder`
     * array. If not provided the order will be according to the order of
     * the fields specified under the `fieldConfig` option, and if none of
     * the above is given then it will fallback to the order in which they
     * are retrieved from the model.
     *
     * @param array $fieldConfig the active field configurations array
     * @param array $attributes  the attributes reference for the model
     * @param array $step        the config for the current step
     *
     * @return null
     */
    private function _sortFields($fieldConfig, &$attributes, $step)
    {
        $defaultOrder = $fieldConfig !== false ? array_keys($fieldConfig) : false;
        $fieldOrder = ArrayHelper::getValue($step, 'fieldOrder', $defaultOrder);

        if ($fieldOrder) {
            $orderedAttributes = [];
            $unorderedAttributes = [];

            array_walk(
                $attributes,
                function (&$item, $index, $fieldOrder) use (
                    &$orderedAttributes,
                    &$unorderedAttributes
                ) {
                    $moveToIndex = array_search($item, $fieldOrder);

                    if ($moveToIndex !== false) {
                        $orderedAttributes[$moveToIndex] = $item;
                    } else {
                        $unorderedAttributes[] = $item;
                    }
                },
                $fieldOrder
            );

            //sort new order according to keys
            ksort($orderedAttributes);

            //merge array with unordered attributes
            $attributes = array_merge($orderedAttributes, $unorderedAttributes);
        }
    }


    /**
     * Check if tabular step has the multiple models if the same type or throw an exception
     *
     * @param array $models the model(s) of the step
     *
     * @return null
     * @throws ArgException
     */
    private function _checkTabularConstraints($models)
    {
        $classes = [];
        foreach ($models as $model) {
            $classes[] = get_class($model);
        }
        $classes = array_unique($classes);

        //check if not a multiple model step with the type set to tabular
        if (sizeof($classes) > 1) {
            throw new ArgException('You cannot have multiple models in a step when the "type" property is set to "tabular", you must provide only a single model or remove the step "type" property.');
        }
        return true;
    }

    /**
     * Adds tabular events for the attribute
     *
     * @param array   $attributeConfig attribute configurations passed
     * @param boolean $isTabularStep   boolean if current step is tabular
     * @param int     $modelIndex      the index of the current model
     * @param string  $attributeId     the id of the current field
     * @param int     $index           the index of the current step
     *
     * @return null
     */
    private function _addTabularEvents($attributeConfig, $isTabularStep, $modelIndex, $attributeId, $index)
    {
        //get the tabular events for the field
        $tabularEvents = ArrayHelper::getValue($attributeConfig, 'tabularEvents', false);

        //check if tabular step and tabularEvents provided for field
        if ($isTabularStep && is_array($tabularEvents) && $modelIndex == 0) {

            //id of the form
            $formId = $this->formOptions['id'];

            //iterate all events attached and bind them
            foreach ($tabularEvents as $eventName => $callback) {
                //get the call back
                $eventCallBack = new JsExpression($callback);

                $this->_bindEvents($eventName, $eventCallBack, $formId, $index, $attributeId);
            }
        }
    }

    /**
     * Binds the tabular events provided by the user
     * 
     * @param string   $eventName     the name of the event to bind
     * @param callable $eventCallBack the callback event provided by the user
     * @param string   $formId        the id of the form
     * @param integer  $index         the current model index
     * @param string   $attributeId   the attribute id to triger the event for
     * 
     * @return null
     */
    private function _bindEvents($eventName, $eventCallBack, $formId, $index, $attributeId)
    {
        $formEvents = [
            'afterInsert' => function ($eventName, $formId, $index, $eventCallBack) {
                $this->_tabularEventJs .= <<<JS
                    $(document).on("formwizard.{$eventName}","#{$formId} #step-{$index} .fields_container>div[id^='row_']",{$eventCallBack});
JS;
            },
            'afterClone' => function ($eventName, $formId, $index, $eventCallBack, $attributeId) {
                $this->_tabularEventJs .= <<<JS
                    $(document).on("formwizard.{$eventName}","#{$formId} #step-{$index} #{$attributeId}",{$eventCallBack});
JS;
            },
            'beforeClone' => function ($eventName, $formId, $index, $eventCallBack, $attributeId) {
                $this->_tabularEventJs .= <<<JS
                    $(document).on("formwizard.{$eventName}","#{$formId} #step-{$index} #{$attributeId}",{$eventCallBack});
JS;
            },
        ];
        //call the event array literals
        isset($formEvents[$eventName]) && $formEvents[$eventName]($eventName, $formId, $index, $eventCallBack, $attributeId);
    }

    /**
     * Adds the restore events for the fields
     *
     * @param array  $attributeConfig the configurations for the attribute
     * @param string $attributeId     the field attribute id
     *
     * @return null
     */
    private function _addRestoreEvents($attributeConfig, $attributeId)
    {
        $persistenceEvents = ArrayHelper::getValue($attributeConfig, 'persistencEvents', []);
        $formId = $this->formOptions['id'];

        foreach ($persistenceEvents as $eventName => $callback) {
            $eventCallBack = new JsExpression($callback);
            $this->_persistenceEvents .= <<<JS
            $(document).on("formwizard.{$formId}.{$eventName}","#{$formId} #{$attributeId}",{$eventCallBack});
JS;
        }
    }

    /**
     * Creates a custom field of the given type
     * 
     * @param string         $fieldType        the type of the field to be created
     * @param array          $fieldTypeOptions the optinos for the field to be created
     * @param boolean|string $hintText         the hint text to be used
     * 
     * @return yii\widgets\ActiveField;
     */
    private function _createField($fieldType, $fieldTypeOptions, $hintText = false)
    {
        $defaultFieldTypes = [
            'text' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];

                return $field->textInput($options)->label($label, $labelOptions);
            },
            'number' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];

                return $field->textInput($options)->label($label, $labelOptions);
            },
            'dropdown' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];
                $itemsList = $params['itemsList'];

                return $field->dropDownList($itemsList, $options)
                    ->label($label, $labelOptions);
            },
            'radio' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];
                $itemsList = $params['itemsList'];

                if (is_array($itemsList)) {
                    return $field->radioList($itemsList, $options)
                        ->label($label, $labelOptions);
                }
                return $field->radio($options);
            },
            'checkbox' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];
                $itemsList = $params['itemsList'];

                //if checkboxList needs to be created
                if (is_array($itemsList)) {
                    return $field->checkboxList($itemsList, $options)
                        ->label($label, $labelOptions);
                }

                //if a single checkbox needs to be created
                $labelNull = $label === null;
                $labelOptionsEmpty = empty($labelOptions);
                $nothingSetByUser = ($labelNull && $labelOptionsEmpty);
                $label = $nothingSetByUser ? false : $label;

                return $field->checkbox($options)->label($label, $labelOptions);
            },
            'textarea' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];

                return $field->textarea($options)->label($label, $labelOptions);
            },
            'file' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];

                return $field->fileInput($options)->label($label, $labelOptions);
            },
            'hidden' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];

                return $field->hiddenInput($options)->label(false);
            },
            'password' => function ($params) {
                $field = $params['field'];
                $options = $params['options'];
                $label = $params['label'];
                $labelOptions = $params['labelOptions'];

                return $field->passwordInput($options)->label($label, $labelOptions);
            }
        ];

        //create field depending on the type of the value provided
        if (isset($defaultFieldTypes[$fieldType])) {

            $field = $defaultFieldTypes[$fieldType]($fieldTypeOptions);
            return (!$hintText) ? $field : $field->hint($hintText);
        }
    }

    /**
     * Generates Html for the step fields
     * 
     * @param array          $attributes    the attributes to iterate
     * @param integer        $modelIndex    the index of the current model
     * @param integer        $index         the index of the current step
     * @param object         $model         the model object
     * @param boolean        $isTabularStep if the current step is tabular
     * @param array          $fieldConfig   customer field confitigurations
     * @param boolean|string $stepHeadings  the headings for the current step
     * 
     * @return mixed
     */
    private function _createStepHtml($attributes, $modelIndex, $index, $model, $isTabularStep, $fieldConfig, $stepHeadings)
    {
        $htmlFields = '';
        //iterate all fields associated to the relevant model
        foreach ($attributes as $attribute) {

            //attribute name
            $isTabularModel = substr((string)$modelIndex, 0, 1) != 'n';
            $attributeName = ($isTabularStep && $isTabularModel) ? "[$modelIndex]" . $attribute : $attribute;
            $customConfigDefinedForField = $fieldConfig && isset($fieldConfig[$attribute]);

            //has heading for the field
            $hasHeading = false !== $stepHeadings;

            //add heading
            if ($hasHeading) {
                $headingFields = ArrayHelper::getColumn($stepHeadings, 'before', true);
                if (in_array($attribute, $headingFields)) {
                    $currentIndex = array_search($attribute, array_values($headingFields));
                    $headingConfig = $stepHeadings[$currentIndex];

                    //add heading
                    $htmlFields .= $this->_addHeading($headingConfig);
                }
            }

            //if custom config available for field
            if ($customConfigDefinedForField) {
                //if filtered field
                $isFilteredField = $fieldConfig[$attribute] === false;

                //skip the field and go to next
                if ($isFilteredField) {
                    continue;
                }

                //custom field population
                $htmlFields .= $this->createCustomInput(
                    $model,
                    $attributeName,
                    $fieldConfig[$attribute]
                );
                //id of the input
                $attributeId = Html::getInputId($model, $attributeName);

                //add tabular events
                $this->_addTabularEvents($fieldConfig[$attribute], $isTabularStep, $modelIndex, $attributeId, $index);

                //add the restore events
                $this->_addRestoreEvents($fieldConfig[$attribute], $attributeId);
            } else {
                //default field population
                $htmlFields .= $this->createDefaultInput($model, $attributeName);
            }

            //add heading (SERGI)
            if ($hasHeading) {
                $headingFields = ArrayHelper::getColumn($stepHeadings, 'after', true);
                if (in_array($attribute, $headingFields)) {
                    $currentIndex = array_search($attribute, array_values($headingFields));
                    $headingConfig = $stepHeadings[$currentIndex];

                    //add heading
                    $htmlFields .= $this->_addHeading($headingConfig);
                }
            }
        }
        return $htmlFields;
    }

    /**
     * Adds heading before the desired field
     *
     * @param array $headingConfig the configuration array
     *
     * @return HTML
     */
    private function _addHeading($headingConfig)
    {
        $headingPrevious = ArrayHelper::getValue($headingConfig, 'previousHTML', '');
        $headingNext = ArrayHelper::getValue($headingConfig, 'nextHTML', '');
        $headingText = Html::encode(ArrayHelper::getValue($headingConfig, 'text', ''));
        $headingRawText = ArrayHelper::getValue($headingConfig, 'rawtext', '');
        $headingClass = ArrayHelper::getValue($headingConfig, 'className', 'field-heading');
        $headingIcon = ArrayHelper::getValue($headingConfig, 'icon', self::ICON_HEADING);

        return $headingPrevious . Html::tag('h3', $headingIcon . $headingText . $headingRawText, ['class' => $headingClass]) . $headingNext;
    }

    /**
     * Parse the configurations for the field
     * 
     * @param array $fieldConfig the configurations array passed by the user
     * 
     * @return array
     */
    private function _parseFieldConfig($fieldConfig)
    {
        //options
        $options = ArrayHelper::getValue($fieldConfig, 'options', []);

        //is multi field name
        $isMultiField = Arrayhelper::getValue($fieldConfig, 'multifield', false);

        //field type
        $fieldType = ArrayHelper::getValue($options, 'type', 'text');

        //widget
        $widget = ArrayHelper::getValue($fieldConfig, 'widget', false);

        //label configuration
        $labelConfig = ArrayHelper::getValue($fieldConfig, 'labelOptions', null);

        //template
        $template = ArrayHelper::getValue(
            $fieldConfig,
            'template',
            "{label}\n{input}\n{hint}\n{error}"
        );

        //container
        $containerOptions = ArrayHelper::getValue(
            $fieldConfig,
            'containerOptions',
            []
        );

        //inputOptions
        $inputOptions = ArrayHelper::getValue($fieldConfig, 'inputOptions', []);

        //items list
        $itemsList = ArrayHelper::getValue($options, 'itemsList', '');

        //label text
        $label = ArrayHelper::getValue($labelConfig, 'label', null);

        //label options
        $labelOptions = ArrayHelper::getValue($labelConfig, 'options', []);

        //get the hint text for the field
        $hintText = ArrayHelper::getValue($fieldConfig, 'hint', false);

        return [$options, $isMultiField, $fieldType, $widget, $template, $containerOptions, $inputOptions, $itemsList, $label, $labelOptions, $hintText];
    }

    /**
     * Creates a default ActiveFieldObject
     *
     * @param object  $model        instance of the current model
     * @param string  $attribute    name of the current field / attribute
     * @param array   $fieldOptions options for the field as in
     *                              \yii\widgets\ActiveField `fieldOptions`
     * @param boolean $isMulti      determines if the field will be using array name
     *                              or not for example : first_name[] will be used
     *                              if true and first_name if false
     *
     * @return \yii\widgets\ActiveField
     */
    public function createField(
        $model,
        $attribute,
        $fieldOptions = [],
        $isMulti = false
    ) {
        return $this->_form->field(
            $model,
            $attribute . ($isMulti ? '[]' : ''),
            $fieldOptions
        );
    }

    /**
     * Creates a default field for the steps if no fields under
     * the activefield config is provided
     *
     * @param object $model     instance of the current model
     * @param string $attribute name of the attribute / field
     *
     * @return \yii\widgets\ActiveField
     */
    public function createDefaultInput($model, $attribute)
    {
        //create field
        $field = $this->createField($model, $attribute);
        return $field->textInput()->label(null, ['class' => 'form-label']);
    }

    /**
     * Filters the step fields for the `except` and `only` options if mentioned
     *
     * @param object $model          instance of the model dedicated for the step
     * @param array  $onlyFields     the field to be populated only
     * @param array  $disabledFields the fields to be ignored
     *
     * @return array $fields
     */
    public function getStepFields($model, $onlyFields, $disabledFields)
    {
        if (!empty($onlyFields)) {
            return array_values(
                array_filter(
                    array_keys($model->getAttributes($model->safeAttributes())),
                    function ($item) use ($onlyFields) {
                        return in_array($item, $onlyFields);
                    }
                )
            );
        }
        return array_filter(
            array_keys($model->getAttributes($model->safeAttributes())),
            function ($item) use ($disabledFields) {
                return !in_array($item, $disabledFields);
            }
        );
    }
}
