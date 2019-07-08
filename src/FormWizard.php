<?php
/**
 * PHP VERSION >=5.6
 *
 * @category  Yii2-Plugin
 * @package   Yii2-formwizard
 * @author    Muhammad Omer Aslam <buttflattery@gmail.com>
 * @copyright 2018 IdowsTECH
 * @license   https://github.com/buttflattery/yii2-formwizard/blob/master/LICENSE
 *            BSD License 3.01
 * @link      https://github.com/buttflattery/yii2-formwizard
 */
namespace buttflattery\formwizard;

use buttflattery\formwizard\assetbundles\bs3\FormWizardAsset as Bs3Assets;
use buttflattery\formwizard\assetbundles\bs4\FormWizardAsset as Bs4Assets;
use Yii;
use yii\base\InvalidArgumentException as ArgException;
use yii\base\Widget;
use yii\bootstrap4\ActiveForm as BS4ActiveForm;
use yii\bootstrap4\BootstrapAsset as BS4Asset;
use yii\bootstrap\ActiveForm as BS3ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\web\View;
use buttflattery\formwizard\traits\WizardTrait;

/**
 * A Yii2 plugin used for creating stepped form or form wizard using
 * yii\widgets\ActiveForm and \yii\db\ActiveRecord, it uses smart wizard
 * library for creating the form interface that uses 3 builtin and 2 extra themes,
 * moreover you can also create your own customized theme too.
 *
 * @category  Yii2-Plugin
 * @package   Yii2-formwizard
 * @author    Muhammad Omer Aslam <buttflattery@gmail.com>
 * @copyright 2018 IdowsTECH
 * @license   https://github.com/buttflattery/yii2-formwizard/blob/master/LICENSE
 *            BSD License 3.01
 * @version   Release: 1.0
 * @link      https://github.com/buttflattery/yii2-formwizard
 */
class FormWizard extends Widget
{

    use WizardTrait;

    /**
     * Holds the ActiveForm object
     *
     * @var mixed
     */
    private $_form;

    /**
     * Holds the collection of fields that are validated
     *
     * @var array
     */
    private $_allFields = [];

    /**
     * The Bootstrap Version to be loaded for the extension
     *
     * @var mixed
     */
    private $_bsVersion;

    /**
     * Used for collecting user provided custom Js for the formwizard.beforeClone event
     *
     * @var mixed
     */
    private $_tabularEventJs;

    /**
     * Used for adding limit var for the tabular steps to be used in javascript 
     * 
     * @var mixed
     */
    private $_rowLimitJs;

    /**
     * Used for collecting user provided callback for the event formwizard.afterRestore
     *
     * @var mixed
     */
    private $_persistenceEvents;


    //options widget

    /**
     * The Main Wizard container id, this is assigned automatically if not assigned
     *
     * @var mixed
     */
    public $wizardContainerId;

    /**
     * The array of steps that are to be created for the FormWizard,
     * this option is compulsary.
     *
     * Example:
     * steps=>[
     *      [
     *          "model"=>$model,
     *          "title"=>"Step Title"
     *      ],
     *      [
     *          "model"=>$modelUser
     *          "title"=>"Step Title"
     *      ]
     * ]
     *
     * @var array
     */
    public $steps = [];

    /**
     * The Options for the ActiveForm see the
     * https://www.yiiframework.com/doc/api/2.0/yii-widgets-activeform
     * for the list of options that you can pass
     *
     * @var array
     */
    public $formOptions = [];

    //plugin options

    /**
     * The theme to be used for the formWizard plugin.
     * The `default` theme is used by Default.
     *
     * @var string
     */
    public $theme = self::THEME_DEFAULT;

    /**
     * The transition effect that is to be used for the steps while changing.
     * The Default is the `silde` effect
     *
     * @var string
     */
    public $transitionEffect = 'slide';

    /**
     * Automatically adjust content height, default value is `true`.`
     *
     * @var boolean
     */
    public $autoAdjustHeight = true;
    /**
     * An array of step numbers to show as disabled,
     * zero based array of step index ex: [2,4]
     *
     * @var array
     */
    public $disabledSteps = [];

    /**
     * Wether to show the step URL Hash in the url hash based on step,
     * Default is `false`
     *
     * @var boolean
     */
    public $showStepURLhash = false;

    /**
     * Enable selection of the step based on url hash, the default is `false`.
     *
     * @var mixed
     */
    public $useURLhash = false;

    /**
     * The position of the toolbar tht holds the buttons Next & Prev.
     *
     * @var string
     */
    public $toolbarPosition = 'top';

    /**
     * The Toolbar Extra buttons to be created.
     *
     * @var mixed
     */
    public $toolbarExtraButtons;

    /**
     * Mark the steps that are completed, default is `true`.
     *
     * @var mixed
     */
    public $markDoneStep = true;

    /**
     * Mark all the previous steps as completed, default is `true`.
     *
     * @var mixed
     */
    public $markAllPreviousStepsAsDone = true;

    /**
     * Mark a step as incomplete if moved to a previuos step. Default is `false`.
     *
     * @var mixed
     */
    public $removeDoneStepOnNavigateBack = true;

    /**
     * Enable/Disable the done steps navigation default is `true`.
     *
     * @var mixed
     */
    public $enableAnchorOnDoneStep = true;

    /**
     * Enable Preview Step option, default value `false`
     *
     * @var boolean
     */
    public $enablePreview = false;

    /**
     * Enables restoring of the data for the unsaved form
     *
     * @var boolean
     */
    public $enablePersistence = false;

    /**
     * The Text label for the Next button. Default is `Next`.
     *
     * @var string
     */
    public $labelNext = 'Next';

    /**
     * The Text label for the Previous button. Default is `Previous`.
     *
     * @var string
     */
    public $labelPrev = 'Previous';

    /**
     * The Text label for the Finish button. Default is `Finish`.
     *
     * @var string
     */
    public $labelFinish = 'Finish';

    /**
     * The label text for the restore button
     *
     * @var string
     */
    public $labelRestore = 'Restore';

    /**
     * The icon for the Next button you want to be shown inside the button.
     * Default is `<i class="formwizard-arrow-right-alt1-ico"></i>`.
     *
     * This can be an html string '<i class="formwizard-arrow-right-alt1-ico"></i>'
     * in case you are using FA,Material or Glyph icons or an image tag
     * like '<img src="/path/to/image" />'.
     *
     * @var mixed
     */
    public $iconNext = self::ICON_NEXT;

    /**
     * The icon for the Previous button you want to be shown inside the button.
     * Default is `<i class="formwizard-arrow-left-alt1-ico"></i>`.
     *
     * This can be an html string '<i class="fa fa-arrow-left"></i>'
     * in case you are using FA,Material or Glyph icons or an image tag
     * like '<img src="/path/to/image" />'.
     *
     * @var mixed
     */
    public $iconPrev = self::ICON_PREV;

    /**
     * The icon for the Previous button you want to be shown inside the button.
     * Default is `<i class="formwizard-check-alt-ico"></i>`.
     *
     * This can be an html string '<i class="fa fa-done"></i>'
     * in case you are using FA,Material or Glyph icons or an image tag
     * like '<img src="/path/to/image" />'.
     *
     * @var mixed
     */
    public $iconFinish = self::ICON_FINISH;

    /**
     * The icon for the Add Row button you want to be shown inside the button.
     * Default is `<i class="formwizard-check-alt-ico"></i>`.
     *
     * This can be an html string '<i class="fa fa-add"></i>'
     * in case you are using FA, Material or Glyph icons, or an
     * image tag like '<img src="/path/to/image" />'.
     *
     * @var mixed
     */
    public $iconAdd = self::ICON_ADD;

    /**
     * The icon for the Restore button you want to be shown inside the button.
     * Default is `<i class="formwizard_restore-ico"></i>`.
     *
     * This can be an html string '<i class="fa fa-restore"></i>'
     * in case you are using FA, Material or Glyph icons, or an
     * image tag like '<img src="/path/to/image" />'.
     *
     * @var mixed
     */
    public $iconRestore = self::ICON_RESTORE;

    /**
     * The class for the Next button , default is `btn btn-info`
     *
     * @var string
     */
    public $classNext = 'btn btn-info ';

    /**
     * The class for the Previous button , default is `btn btn-info`
     *
     * @var string
     */
    public $classPrev = 'btn btn-info ';

    /**
     * The class for the Finish button, default is `btn btn-success`
     *
     * @var string
     */
    public $classFinish = 'btn btn-success ';

    /**
     * The class for the Add Row button, default is btn btn-info
     *
     * @var string
     */
    public $classAdd = 'btn btn-info ';

    /**
     * The class for the Add Row button, default is btn btn-info
     *
     * @var string
     */
    public $classRestore = 'btn btn-success ';

    /**
     * @var string
     */
    public $classListGroup = 'list-group';

    /**
     * @var string
     */
    public $classListGroupHeading = 'list-group-heading';

    /**
     * @var string
     */
    public $classListGroupItem = 'list-group-item-success';

    /**
     * @var string
     */
    public $classListGroupBadge = 'success';


    /**
     * ICONS
     * */

    const ICON_NEXT = '<i class="formwizard-arrow-right-alt1-ico"></i>';
    const ICON_PREV = '<i class="formwizard-arrow-left-alt1-ico"></i>';
    const ICON_FINISH = '<i class="formwizard-check-alt-ico"></i>';
    const ICON_ADD = '<i class="formwizard-plus-ico"></i>';
    const ICON_RESTORE = '<i class="formwizard-restore-ico"></i>';
    const ICON_HEADING = '<i class="formwizard-quill-ico"></i>';

    /**
     * STEP TYPES
     * */
    const STEP_TYPE_DEFAULT = 'default';
    const STEP_TYPE_TABULAR = 'tabular';
    const STEP_TYPE_PREVIEW = 'preview';

    const ROWS_UNLIMITED = '-1';

    /**
     * THEMES
     * */
    const THEME_DEFAULT = 'default';
    const THEME_DOTS = 'dots';
    const THEME_ARROWS = 'arrows';
    const THEME_CIRCLES = 'circles';
    const THEME_MATERIAL = 'material';
    const THEME_MATERIAL_V = 'material-v';
    const THEME_TAGS = 'tags';

    /**
     * Supported themes for the Widget, default value used is `default`.
     *
     * @var array
     */
    protected $themesSupported = [
        self::THEME_DOTS => 'Dots',
        self::THEME_CIRCLES => 'Circles',
        self::THEME_ARROWS => 'Arrows',
        self::THEME_MATERIAL => 'Material',
        self::THEME_MATERIAL_V => 'MaterialVerticle',
        self::THEME_TAGS => 'Tags'
    ];

    /**
     * Initializes the plugin
     *
     * @return null
     */
    public function init()
    {
        parent::init();
        $this->_setDefaults();
    }

    /**
     * Sets the defaults for the widget and detects to
     * use which version of Bootstrap.
     *
     * @return null
     * @throws ArgException
     */
    private function _setDefaults()
    {
        if (empty($this->steps)) {
            throw new ArgException('You must provide steps for the form.');
        }

        //set the form id for the form if not set by the user
        if (!isset($this->formOptions['id'])) {
            $this->formOptions['id'] = $this->getId() . '_form_wizard';
        } else {
            preg_match('/\b(\w+)\b/', $this->formOptions['id'], $matches);

            if ($matches[0] !== $this->formOptions['id']) {
                throw new ArgException(
                    'You must provide the id for the form that matches
                    any word character (equal to [a-zA-Z0-9_])'
                );
            }
        }

        //widget container ID
        if (!isset($this->wizardContainerId)) {
            $this->wizardContainerId = $this->getId() . '-form_wizard_container';
        }

        //theme buttons material
        if ($this->theme == self::THEME_MATERIAL || $this->theme == self::THEME_MATERIAL_V) {
            $this->classNext .= 'waves-effect';
            $this->classPrev .= 'waves-effect';
            $this->classFinish .= 'waves-effect';
        }

        //is bs4 version
        $isBs4 = class_exists(BS4Asset::class);
        $this->_bsVersion = $isBs4 ? 4 : 3;
    }

    /**
     * Retrives the plugin default options to be initiazed with
     *
     * @return array $options
     */
    public function getPluginOptions()
    {
        return [
            'selected' => 0,
            'keyNavigation' => false,
            'autoAdjustHeight' => $this->autoAdjustHeight,
            'disabledSteps' => $this->disabledSteps,
            'backButtonSupport' => false,
            'theme' => $this->theme,
            'transitionEffect' => $this->transitionEffect,
            'showStepURLhash' => $this->showStepURLhash,
            'toolbarSettings' => [
                'toolbarPosition' => $this->toolbarPosition,
                'showNextButton' => false,
                'showPreviousButton' => false,
                'toolbarExtraButtons' => $this->toolbarExtraButtons
            ],
            'anchorSettings' => [
                'anchorClickable' => false,
                'enableAllAnchors' => false,
                'markDoneStep' => $this->markDoneStep,
                'markAllPreviousStepsAsDone' => $this->markAllPreviousStepsAsDone,
                'removeDoneStepOnNavigateBack' => $this->removeDoneStepOnNavigateBack,
                'enableAnchorOnDoneStep' => $this->enableAnchorOnDoneStep
            ]
        ];
    }

    /**
     * Runs the widget.
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function run()
    {
        parent::run();

        $wizardContainerId = $this->wizardContainerId;

        $pluginOptions = $this->getPluginOptions();
        $jsOptionsPersistence = Json::encode($this->enablePersistence);

        $jsButton = <<< JS
        $.formwizard.helper.appendButtons({
            form:'#{$this->formOptions["id"]}',
            labelNext:'{$this->labelNext}',
            labelPrev:'{$this->labelPrev}',
            labelFinish:'{$this->labelFinish}',
            labelRestore:'{$this->labelRestore}',
            iconNext:'{$this->iconNext}',
            iconPrev:'{$this->iconPrev}',
            iconFinish:'{$this->iconFinish}',
            iconRestore:'{$this->iconRestore}',
            classNext:'{$this->classNext}',
            classPrev:'{$this->classPrev}',
            classFinish:'{$this->classFinish}',
            classRestore:'{$this->classRestore}',
            enablePersistence:{$jsOptionsPersistence},

        }).concat({$pluginOptions['toolbarSettings']['toolbarExtraButtons']})
JS;

        $pluginOptions['toolbarSettings']['toolbarExtraButtons'] = new JsExpression($jsButton);
        //if bootstrap3 loaded
        $isBs3 = $this->_bsVersion == 3;

        if ($isBs3) {
            $activeForm = BS3ActiveForm::class;
        } else {
            $activeForm = BS4ActiveForm::class;
        }

        //start ActiveForm tag
        $this->_form = $activeForm::begin($this->formOptions);

        //start container tag
        echo Html::beginTag('div', ['id' => $wizardContainerId]);

        //draw form steps
        echo $this->createFormWizard();

        //end container div tag
        echo Html::endTag('div');

        //end form tag
        $this->_form->end();

        //get current view object
        $view = $this->getView();

        //get all fields json for javascript processing
        $fieldsJSON = Json::encode($this->_allFields);

        //encode plugin options
        $pluginOptionsJson = Json::encode($pluginOptions);

        $this->registerScripts();
        //add tabular events call back js
        $js = $this->_tabularEventJs;
        $js .= $this->_persistenceEvents;

        //init script for the wizard
        $js .= <<<JS

        //start observer for the smart wizard to run the script
        //when the child HTMl elements are populated
        //necessary for material themes and the button
        //events for tabular row
        $.formwizard.observer.start('#{$wizardContainerId}');

        // Step show event
        $.formwizard.helper.updateButtons('#{$wizardContainerId}');

        // Smart Wizard
        $('#{$wizardContainerId}').smartWizard({$pluginOptionsJson});

        //bind Yii ActiveForm event afterValidate to check
        //only current steps fields for validation and allow to next step
        if($('#{$this->formOptions["id"]}').yiiActiveForm('data').attributes.length){
            $.formwizard.validation.bindAfterValidate('#{$this->formOptions["id"]}');
        }

        //fields list
        $.formwizard.fields.{$this->formOptions['id']}={$fieldsJSON};

        $.formwizard.options.{$this->formOptions['id']}={
            wizardContainerId:'{$wizardContainerId}',
            classAddRow:'{$this->classAdd}',
            labelNext:'{$this->labelNext}',
            labelPrev:'{$this->labelPrev}',
            labelFinish:'{$this->labelFinish}',
            iconNext:'{$this->iconNext}',
            iconPrev:'{$this->iconPrev}',
            iconFinish:'{$this->iconFinish}',
            iconAdd:'{$this->iconAdd}',
            classNext:'{$this->classNext}',
            classPrev:'{$this->classPrev}',
            classFinish:'{$this->classFinish}',
            enablePreview:'{$this->enablePreview}',
            bsVersion:'{$this->_bsVersion}',
            classListGroup:'{$this->classListGroup}',
            classListGroupHeading:'{$this->classListGroupHeading}',
            classListGroupItem:'{$this->classListGroupItem}',
            classListGroupBadge:'{$this->classListGroupBadge}'
        };

        //init the data persistence if enabled

        if(true =={$jsOptionsPersistence}){
            $.formwizard.persistence.init('{$this->formOptions["id"]}');
        }

JS;

        //register script
        $view->registerJs($js, View::POS_READY);
    }

    /**
     * Creates the form wizard
     *
     * @return HTML
     */
    public function createFormWizard()
    {
        //get the steps
        $steps = $this->steps;

        //start tabs html
        $htmlTabs = Html::beginTag('ul');

        //start Body steps html
        $htmlSteps = Html::beginTag('div');

        if ($this->enablePreview) {
            $steps = array_merge(
                $steps,
                [
                    [
                        'type' => self::STEP_TYPE_PREVIEW,
                        'title' => 'Final Preview',
                        'description' => 'Final Preview of all Steps',
                        'formInfoText' => 'Click any of the steps below to edit them'
                    ]
                ]
            );
        }

        //loop thorugh all the steps
        foreach ($steps as $index => $step) {

            //create wizard steps
            list($tabs, $steps) = $this->createStep($index, $step);

            $htmlTabs .= $tabs;
            $htmlSteps .= $steps;
        }

        //end tabs html
        $htmlTabs .= Html::endTag('ul');

        //end steps html
        $htmlSteps .= Html::endTag('div');

        $content = $htmlTabs . $htmlSteps;

        //return form wizard html
        return $content;
    }

    /**
     * Creates the single step in the form wizard
     *
     * @param int   $index index of the current step
     * @param array $step  config for the current step
     *
     * @return array
     */
    public function createStep($index, $step)
    {
        //step title
        $stepTitle = ArrayHelper::getValue($step, 'title', 'Step-' . ($index + 1));

        //step description
        $stepDescription = ArrayHelper::getValue($step, 'description', 'Description');

        //form body info text
        $formInfoText = ArrayHelper::getValue($step, 'formInfoText', 'Add details below');

        //get html tabs
        $htmlTabs = $this->createTabs($index, $stepDescription, $stepTitle);

        //get html body
        $htmlBody = $this->createBody($index, $formInfoText, $step);

        //return html
        return [$htmlTabs, $htmlBody];
    }

    /**
     * Creates the tabs for the formwizard
     *
     * @param int    $index           index of the current step
     * @param string $stepDescription description text for the tab
     * @param string $stepTitle       step title to be displayed inside the tab
     *
     * @return HTML
     */
    public function createTabs($index, $stepDescription, $stepTitle)
    {
        $html = '';

        //make tabs
        $html .= Html::beginTag('li');
        $html .= Html::beginTag('a', ['href' => '#step-' . $index]);
        $html .= $stepTitle . '<br />';
        $html .= Html::tag('small', $stepDescription);
        $html .= Html::endTag('a');
        $html .= Html::endTag('li');

        return $html;
    }

    /**
     * Create the body for the Step
     *
     * @param int    $index        index of the current step
     * @param string $formInfoText description text for the form displayed
     *                             on top of the fields
     * @param array  $step         the config for the current step
     *
     * @return HTML $html
     */
    public function createBody($index, $formInfoText, $step)
    {
        $html = '';

        //get the step type
        $stepType = ArrayHelper::getValue($step, 'type', self::STEP_TYPE_DEFAULT);

        //check if tabular step
        $isTabularStep = $stepType == self::STEP_TYPE_TABULAR;

        //tabular rows limit
        $limitRows = ArrayHelper::getValue($step, 'limitRows', self::ROWS_UNLIMITED);

        //hideTabularButtons
        $hideTabularButtons = ArrayHelper::getValue($step, 'hideTabularButtons', false);

        //check if tabular step
        if ($isTabularStep) {
            $this->_checkTabularConstraints($step['model']);
        }

        //step data
        $dataStep = [
            'number' => $index,
            'type' => $stepType
        ];

        //start step wrapper div
        $html .= Html::beginTag(
            'div',
            ['id' => 'step-' . $index, 'data' => ['step' => Json::encode($dataStep)]]
        );

        $html .= Html::tag('div', $formInfoText, ['class' => 'border-bottom border-gray pb-2']);

        //Add Row Buton to add fields dynamically
        if ($isTabularStep && !$hideTabularButtons) {
            $html .= Html::button(
                $this->iconAdd . '&nbsp;Add',
                [
                    'class' => $this->classAdd . (($this->_bsVersion == 3) ? ' pull-right add_row' : ' float-right add_row')
                    // 'id'=>'add_row'
                ]
            );
        }

        if (!empty($step['model'])) {
            //start field container tag <div class="fields_container">
            $html .= Html::beginTag('div', ["class" => "fields_container", 'data' => ['rows-limit' => $limitRows]]);
            //create step fields
            $html .= $this->createStepFields($index, $step, $isTabularStep, $limitRows);
        }

        //close the field container tag </div>
        $html .= Html::endTag('div');

        //close the step div </div>
        $html .= Html::endTag('div');
        return $html;
    }

    /**
     * Creates the fields for the current step
     *
     * @param int     $index         index of the current step
     * @param array   $step          config for the current step
     * @param boolean $isTabularStep if the current step is tabular or not
     * @param int     $limitRows     the rows limit for the tabular step
     *
     * @return HTML
     */
    public function createStepFields($index, $step, $isTabularStep, $limitRows)
    {

        $htmlFields = '';

        //field configurations
        $fieldConfig = ArrayHelper::getValue($step, 'fieldConfig', false);

        //disabled fields
        $disabledFields = ArrayHelper::getValue($fieldConfig, 'except', []);

        //only fields
        $onlyFields = ArrayHelper::getValue($fieldConfig, 'only', []);

        //is array of models
        $isArrayOfModels = is_array($step['model']);

        //hideTabularButtons
        $hideTabularButtons = ArrayHelper::getValue($step, 'hideTabularButtons', false);

        $models = $step['model'];

        if (!$isArrayOfModels) {
            $models = [$step['model']];
        }

        //current step fields
        $fields = [];

        //iterate models
        foreach ($models as $modelIndex => $model) {

            //get safe attributes
            $attributes = $this->getStepFields($model, $onlyFields, $disabledFields);

            //get the step headings
            $stepHeadings = ArrayHelper::getValue($step, 'stepHeadings', false);

            //field order
            $this->_sortFields($fieldConfig, $attributes, $step);

            //add all the field ids to array
            $fields = array_merge(
                $fields,
                array_map(
                    function ($element) use ($model, $isTabularStep, $modelIndex) {
                        return Html::getInputId($model, ($isTabularStep) ? "[$modelIndex]" . $element : $element);
                    },
                    $attributes
                )
            );

            //is tabular step
            if ($isTabularStep) {

                //limit not exceeded
                if ($limitRows === self::ROWS_UNLIMITED || $limitRows > $modelIndex) {
                    //start the row constainer
                    $htmlFields .= Html::beginTag('div', ['id' => 'row_' . $modelIndex, 'class' => 'tabular-row']);

                    if (!$hideTabularButtons) {
                        //add the remove icon if edit mode and more than one rows
                        ($modelIndex > 0) && $htmlFields .= Html::tag('i', '', ['class' => 'remove-row formwizard-x-ico', 'data' => ['rowid' => $modelIndex]]);
                    }
                } else {
                    //terminate the loop for the tabular step if the limit exceeds
                    break;
                }
            }

            //generate the html for the step 
            $htmlFields .= $this->_createStepHtml($attributes, $modelIndex, $index, $model, $isTabularStep, $fieldConfig, $stepHeadings);

            //is tabular step
            if ($isTabularStep) {

                //close row div
                $htmlFields .= Html::endTag('div');
            }
        }

        //copy the fields to the javascript array for validation
        $this->_allFields[$index] = $fields;

        return $htmlFields;
    }

    /**
     * Creates a customized input field according to the
     * structured option for the steps by user
     *
     * @param object $model       instance of the current model
     * @param string $attribute   name of the current field
     * @param array  $fieldConfig config for the current field
     *
     * @return \yii\widgets\ActiveField
     */
    public function createCustomInput($model, $attribute, $fieldConfig)
    {

        //get the options
        list(
            $options, $isMultiField, $fieldType, $widget, $template, $containerOptions, $inputOptions, $itemsList, $label, $labelOptions, $hintText
        ) = $this->_parseFieldConfig($fieldConfig);

        //create field
        $field = $this->createField(
            $model,
            $attribute,
            [
                'template' => $template,
                'options' => $containerOptions,
                'inputOptions' => $inputOptions
            ],
            $isMultiField
        );

        //if label is Closure
        if ($label instanceof \Closure) {
            $label = call_user_func($label, $model);
        }

        //widget
        if ($widget) {
            $field = $field->widget($widget, $options)->label($label, $labelOptions);
            return (!$hintText) ? $field : $field->hint($hintText);
        }

        //remove the type and itemList from options list
        if (isset($options['type']) && $options['type'] !== 'number') {
            unset($options['type']);
        }

        //unset the itemsList from the options list
        unset($options['itemsList']);

        //init the options for the field types
        $fieldTypeOptions = [
            'field' => $field,
            'options' => $options,
            'labelOptions' => $labelOptions,
            'label' => $label,
            'itemsList' => $itemsList
        ];

        //creae the field
        return $this->_createField($fieldType, $fieldTypeOptions, $hintText);
    }

    /**
     * Registers the necessary AssetBundles for the widget
     *
     * @return null
     */
    public function registerScripts()
    {
        $view = $this->getView();

        //register theme specific files
        $themeSelected = $this->theme;

        //register plugin assets
        $this->_bsVersion == 3
            ?
            Bs3Assets::register($view)
            : Bs4Assets::register($view);

        //is supported theme
        if (in_array($themeSelected, array_keys($this->themesSupported))) {
            $themeAsset = __NAMESPACE__ . '\assetbundles\bs' .
                $this->_bsVersion . '\Theme' .
                $this->themesSupported[$themeSelected] . 'Asset';

            $themeAsset::register($view);
        }
    }
}
