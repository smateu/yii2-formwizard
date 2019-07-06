/*jshint esversion: 6 */
/*globals $:true, */

if (typeof jQuery === "undefined") {
    throw new Error("jQuery plugins need to be before formizard.js file.");
}

$.formwizard = {
    triggerEvent: (event, eventSelector, eventParams) => {
        $(eventSelector).trigger(event, eventParams);
    },
    currentButtonTarget: null,
    observerObj: null,
    fields: [],
    options: [],
    submit: false,
    helper: {
        showMessage: message => {
            alert(message);
        },
        removeField: element => {
            let formId = $(element)
                .closest("form")
                .attr("id");
            let currentIndex = $.formwizard.helper.currentIndex("#" + formId);
            let field = $(element).attr("id");
            let currentStepFields = $.formwizard.fields[formId][currentIndex];

            //update fields array for the current form wizard
            $.formwizard.fields[formId][currentIndex] = $.grep(
                currentStepFields,
                function (value) {
                    return value != field;
                }
            );
        },
        addField: (formId, element, currentStep) => {
            $.formwizard.fields[formId][currentStep].push(element.id);
        },
        shake: function (form) {
            $(form + " .sw-main").addClass("shake animated");
            setTimeout(function () {
                $(form + " .sw-main").removeClass("shake animated");
            }, 1000);
        },
        appendButtons: function ({
            form,
            labelNext,
            labelPrev,
            labelFinish,
            labelRestore,
            iconNext,
            iconPrev,
            iconFinish,
            iconRestore,
            classNext,
            classPrev,
            classFinish,
            classRestore,
            enablePersistence = false
        }) {
            let buttons = [];

            if (enablePersistence) {
                buttons.push(
                    $(
                        '<button class="formwizard_restore" type="button"/></button>'
                    )
                    .html(iconRestore + "&nbsp;" + labelRestore)
                    .addClass(classRestore)
                );
            }

            //add to buttons array
            buttons.push(
                $('<button class="formwizard_prev"></button>')
                .html(iconPrev + "&nbsp;" + labelPrev)
                .addClass(classPrev)
                .on("click", function (e) {
                    e.preventDefault();
                    $.formwizard.formNavigation.previous(e.target);
                })
            );

            // Toolbar next, previous and finish custom buttons
            let formwizardBtnNext = $('<button class="formwizard_next"></button>')
                .html(labelNext + "&nbsp" + iconNext)
                .addClass(classNext);

            //add to return array
            buttons.push(
                formwizardBtnNext
            );

            let formwizardBtnFinish = $(
                    '<button class="formwizard_finish" type="submit"/></button>'
                )
                .html(iconFinish + "&nbsp;" + labelFinish)
                .addClass(classFinish);
            //add to buttons array
            buttons.push(
                formwizardBtnFinish
            );

            var combined = formwizardBtnNext.add(formwizardBtnFinish);

            $(combined).on("click", function (e) {
                e.preventDefault();
                if ($(form).yiiActiveForm("data").attributes.length) {
                    return $.formwizard.validation.run(form, e);
                }
                if ($(e.target).hasClass("formwizard_finish")) {
                    $(form).yiiActiveForm("submitForm");
                }
                return $.formwizard.formNavigation.next(e.target);
            });

            return buttons;
        },
        updateButtons: function (wizardContainerId) {
            $(wizardContainerId).on("showStep", function (
                e,
                anchorObject,
                stepNumber,
                stepDirection,
                stepPosition
            ) {
                let btnPrev = $(
                    wizardContainerId +
                    " > .sw-toolbar > .sw-btn-group-extra button.formwizard_prev"
                );
                let btnFinish = $(
                    wizardContainerId +
                    " > .sw-toolbar > .sw-btn-group-extra >button.formwizard_finish"
                );
                let btnNext = $(
                    wizardContainerId +
                    " > .sw-toolbar > .sw-btn-group-extra >button.formwizard_next "
                );

                if (stepPosition === "first") {
                    btnPrev.addClass("disabled");
                    btnFinish.addClass("hidden d-none");
                    btnNext.removeClass("hidden d-none");
                } else if (stepPosition === "final") {
                    btnNext.addClass("hidden d-none");
                    btnFinish.removeClass("hidden d-none");
                    btnPrev.removeClass("disabled hidden d-none");
                    //call preview step if enabled
                    $.formwizard.helper.addPreviewStep(wizardContainerId);
                } else {
                    btnPrev.removeClass("disabled");
                    btnNext.removeClass("disabled hidden d-none");
                    btnFinish.addClass("hidden d-none");
                }

            });
        },
        currentIndex: function (form) {
            return $(form + " ul.step-anchor>li.active").index();
        },
        addPreviewStep: (wizardContainerId) => {
            let formwizardOptions = $.formwizard.options;
            let formId = $(wizardContainerId).closest('form').attr('id');
            let fragment = document.createDocumentFragment();
            let currentStep = $.formwizard.helper.currentIndex('#' + formId);
            let stepContainer = document.querySelector('#step-' + currentStep);
            let bsVersion = formwizardOptions[formId].bsVersion;
            let classListGroup = formwizardOptions[formId].classListGroup;
            let classListGroupHeading = formwizardOptions[formId].classListGroupHeading;

            stepContainer.querySelectorAll("." + classListGroup).forEach(element => {
                element.remove();
            });

            if (formwizardOptions.hasOwnProperty(formId) && formwizardOptions[formId].enablePreview) {
                let fields = $.formwizard.fields[formId];
                fields.forEach(function (stepFields, step) {
                    let stepPreviewContainer = document.createElement("div");
                    stepPreviewContainer.setAttribute('class', classListGroup + ' preview-container');
                    stepPreviewContainer.dataset.step = step;
                    let rowHtml = '<h4 class="' + classListGroupHeading + '">Step ' + parseInt(step + 1) + '</h4>';
                    stepFields.forEach(function (fieldName, index) {
                        let inputLabel = $.formwizard.helper.getpreviewInputLabel(fieldName);
                        let inputValue = $.formwizard.helper.getpreviewInputValue(formId, fieldName);
                        let stepData = {
                            label: inputLabel == '' ? 'NA' : inputLabel,
                            value: inputValue == '' ? 'NA' : inputValue
                        };

                        rowHtml += $.formwizard.helper.previewTemplate(stepData, bsVersion, formwizardOptions[formId]);
                    });

                    stepPreviewContainer.innerHTML = rowHtml;
                    fragment.appendChild(stepPreviewContainer);
                });

                stepContainer.appendChild(fragment);
                $(".preview-button").on('click', function (e) {
                    let stepNo = $(this).closest('div.preview-container').data('step');
                    $.formwizard.formNavigation.goToStep(wizardContainerId, stepNo);
                });
            }
        },
        getpreviewInputLabel: (fieldName) => {
            let text = $('#' + fieldName).siblings('label').text();
            if (text !== '') {
                return text;
            }
            return $('#' + fieldName).attr("placeholder");
        },
        getpreviewInputValue: (formId, fieldName) => {
            let inputType = $('#' + formId + ' #' + fieldName);

            if (inputType.is("select")) {
                // <select> element.
                return $('#' + formId + ' #' + fieldName + ' option:selected').text();
            } else {
                // <textarea> element.
                return inputType.val();
            }
        },
        previewTemplate: (params, bsVersion, formwizardOptions) => {
            let bsClass = bsVersion == 4 ? 'list-group-item-action' : '';
            return `<button type="button" class="list-group-item ${formwizardOptions.classListGroupItem} ${bsClass} preview-button">
                    <span class="badge ${formwizardOptions.classListGroupBadge}">
                        ${params.label}
                    </span>
                    ${params.value}
                    </button>`;
        }
    },
    validation: {
        run: function (form, event) {
            $.formwizard.currentButtonTarget = event.target;
            $(form).yiiActiveForm("validate", true);
        },
        bindAfterValidate: function (form) {
            $(form)
                .on("afterValidate", function (event, messages, errorAttributes) {
                    event.preventDefault();
                    let formName = $(this).attr("id");
                    let currentIndex = $.formwizard.helper.currentIndex(form);
                    const isLastStep = currentIndex == $(form + " .step-anchor").find("li").length - 1;
                    let res;

                    if (!$(form).data('yiiActiveForm').submitting) {
                        return false;
                    }

                    //check if the preview step then skip validation messages check
                    if ($.formwizard.options[formName].enablePreview && isLastStep) {
                        res = 0;
                    } else {
                        res = $.formwizard.fields[formName][currentIndex].diff(messages);
                    }

                    if (!res.length) {
                        //check if last step then submit form

                        if (isLastStep) {
                            $.formwizard.submit = true;
                            return true;
                        } else {
                            $(form).yiiActiveForm("resetForm");
                            $.formwizard.formNavigation.next(
                                $.formwizard.currentButtonTarget
                            );
                        }
                    } else {
                        $.formwizard.helper.shake(form);
                    }
                    return false;
                })
                .on("beforeSubmit", function (event) {
                    event.preventDefault();
                    if ($.formwizard.submit) {
                        $.formwizard.persistence.clearStorage();
                        return true;
                    }
                    return false;
                });
        },
        isValid: function (messages) {
            for (var i in messages) {
                if (messages[i].length > 0) {
                    return true;
                }
            }
            return false;
        },
        updateErrorMessages: function (form, messages) {
            for (var i in messages) {
                if (messages[i].length) {
                    let attribute = i.replace(/\-([0-9]+)/g, "");
                    $(form).yiiActiveForm("updateAttribute", attribute, messages[i]);
                }
            }
        },
        addField: (form, fieldOptions) => {
            $("#" + form).yiiActiveForm("add", fieldOptions);
        },
        removeField: (element) => {
            let formId = $(element)
                .closest("form")
                .attr("id");
            $("#" + formId).yiiActiveForm("remove", element.id);
        }
    },
    formNavigation: {
        next: (target) => {

            let containerId = $(target)
                .parent()
                .closest(".sw-main")
                .attr("id");
            $("#" + containerId).smartWizard("next");
        },
        goToStep: (wizardContainerId, stepno) => {
            $(wizardContainerId).smartWizard("goToStep", stepno);
        },
        previous: (target) => {
            let containerId = $(target)
                .parent()
                .closest(".sw-main")
                .attr("id");
            $("#" + containerId).smartWizard("prev");
        }
    },
    observer: {
        start: function (selector) {
            // select the target node in select2
            var target = document.querySelector(selector);

            $.formwizard.observerObj = $.formwizard.observer.observerInstance(selector);

            // configuration of the observer:
            var config = {
                childList: true,
                attributes: true,
                subtree: false
            };

            // pass in the target node, as well as the observer options
            $.formwizard.observerObj.observe(target, config);
        },

        /**
         *
         * @returns {MutationObserver}
         */
        observerInstance: function (selector) {
            // create an observer instance
            return new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.type == "childList") {

                        //check if material theme used 
                        if (typeof $.themematerial !== 'undefined') {
                            $.themematerial.init();
                        }

                        //init the button events for thetabular steps
                        $.formwizard.init(selector);
                        $.formwizard.observerObj.disconnect();
                    }
                });
            });
        }
    },
    tabular: {
        addRow: element => {
            let currentContainer = $(element).siblings(".fields_container");
            let currentIndex = currentContainer.find(".tabular-row").length;
            let limitOver = currentIndex === currentContainer.data('rows-limit');

            //if row limit exceeded
            if (limitOver) {
                $.formwizard.helper.showMessage("Cannot add any more.");
                return;
            }

            let documentFragment = document.createDocumentFragment();
            let row = $(currentContainer)[0].firstChild;
            let formId = $(element)
                .closest("form")
                .attr("id");
            let currentStep = $.formwizard.helper.currentIndex("#" + formId);
            let tabular = $.formwizard.tabular;

            //get all inputs 
            let oldFieldCollection = $(row).find('input,select,textarea');
            let eventTrigger = $.formwizard.triggerEvent;


            //trigger beforeClone event for all the inputs inside the tabular row to be cloned
            oldFieldCollection.each(function (index, element) {
                if (typeof $(element).attr('id') !== 'undefined') {
                    //trigger beforeclone event
                    eventTrigger("formwizard.beforeClone", "#" + formId + " #step-" + currentStep + " #" + element.id);
                }
            });

            //clone node
            documentFragment.appendChild(row.cloneNode(true));

            //trigger afterClone event for all the inputs inside the tabular row
            oldFieldCollection.each(function (index, element) {
                if (typeof $(element).attr('id') !== 'undefined') {
                    //trigger beforeclone event
                    eventTrigger("formwizard.afterClone", "#" + formId + " #step-" + currentStep + " #" + element.id);
                }
            });

            let rowClone = documentFragment.querySelector("div.tabular-row");

            //update row container id
            rowClone.id = rowClone.id.replace(
                /\_[^\_]+$/,
                "_" + parseInt(currentIndex)
            );

            let newFields = [];
            //update input ids
            documentFragment
                .querySelectorAll("input,select,textarea")
                .forEach(function (element, index) {

                    //save old id
                    let oldFieldId = element.id;

                    //update input attributes
                    tabular.updateFieldAttributes(element, currentIndex);
                    if (typeof $(element).attr('id') !== 'undefined') {
                        //get the default field options ActiveForm
                        let fieldOptions = tabular.setFieldDefaults(
                            element,
                            formId,
                            oldFieldId
                        );

                        //add field to the formwizard step fields list
                        $.formwizard.helper.addField(formId, element, currentStep);
                        newFields.push(element.id);
                        if (typeof fieldOptions !== 'undefined') {
                            //add field to the activeform validation
                            $.formwizard.validation.addField(formId, fieldOptions);
                        }
                    }
                });

            //add the remove button
            let removeIcon = document.createElement("i");
            removeIcon.className = "remove-row formwizard-x-ico";
            removeIcon.dataset.rowid = currentIndex;

            rowClone.insertBefore(removeIcon, rowClone.firstChild);
            $(currentContainer)[0].appendChild(documentFragment);

            //trigger the afterInsert event 
            eventTrigger("formwizard.afterInsert", "#" + formId + " #step-" + currentStep + " #row_" + currentIndex, {
                rowIndex: currentIndex
            });
        },
        removeRow: rowid => {
            let rowContainer = $("#row_" + rowid);
            rowContainer.find("textarea,input,select").each(function (index, element) {

                //remove from the fromwizard field list
                $.formwizard.helper.removeField(element);

                //remove from the ActiveForm validation list
                $.formwizard.validation.removeField(element);
            });

            rowContainer.remove();
        },
        setFieldDefaults: (element, formId, oldFieldId) => {
            // get then name only for the tabular input
            let nameOnly = element.name.match(/(\[[\d]{0,}\].*)/);
            let fieldProperty = $("#" + formId).yiiActiveForm("find", oldFieldId);
            let fieldOptions;

            if (typeof fieldProperty !== 'undefined') {
                //set the default options for the ActiveForm field
                fieldOptions = {
                    id: element.id,
                    name: nameOnly[0],
                    container: ".field-" + element.id,
                    input: "#" + element.id,
                    error: ".help-block.help-block-error",
                    value: element.value,
                    status: 0,
                    validate: fieldProperty.hasOwnProperty('validate') ? fieldProperty.validate : function (attribute, value, messages, deferred, $form) {}
                };
            }

            return fieldOptions;

        },
        updateFieldAttributes: (element, currentIndex) => {
            let hasContainer = $(element)
                .parent()
                .hasClass("field-" + element.id);

            if (typeof $(element).attr('id') !== 'undefined') {
                //update counter of the id
                element.id = element.id.replace(
                    /\-([\d]+)\-/,
                    "-" + parseInt(currentIndex) + "-"
                );
            }


            //update the counter of the name
            element.name = element.name.replace(
                /\[([\d]+)\]/,
                "[" + parseInt(currentIndex) + "]"
            );

            //reset value
            element.value = "";

            //if the field container is available
            if (hasContainer) {
                let container = $(element).parent();
                $(container).removeClassesExceptThese(["form-group", "required"]);
                $(container).addClass("field-" + element.id);
                $(container)
                    .find("label")
                    .attr("for", element.id);
                $(container)
                    .find(".help-block")
                    .html("");
            }
        }
    },
    init: (selector) => {

        $(selector).on("click", ".remove-row", function (e) {
            $.formwizard.tabular.removeRow($(this).data("rowid"));
        });

        $(selector + " .add_row").on("click", function (e) {
            $.formwizard.tabular.addRow($(this));
        });
    },
    persistence: {
        assign: (obj, keyPath, value) => {
            lastKeyIndex = keyPath.length - 1;
            for (var i = 0; i < lastKeyIndex; ++i) {
                key = keyPath[i];
                if (!(key in obj)) {
                    obj[key] = {};
                }
                obj = obj[key];
            }
            obj[keyPath[lastKeyIndex]] = value;
        },
        storageFields: {},
        savefield: (fieldObject, formId, stepData) => {
            let fieldId = fieldObject.id;
            let fieldType = $(fieldObject).get(0).type;
            let stepNumber = stepData.number;
            let stepType = stepData.type;


            if (!$.formwizard.persistence.storageFields.hasOwnProperty('step-' + stepNumber)) {
                //set the step type
                $.formwizard.persistence.storageFields['step-' + stepNumber] = {
                    stepType: stepType,
                    fields: {}
                };
            }


            let fieldTypes = {
                'select-one': (fieldId) => {
                    if ($.formwizard.persistence.storageFields['step-' + stepNumber].stepType == 'tabular') {
                        let rowId = $("#" + formId + " #" + fieldId).closest('div.tabular-row').attr('id');
                        if (!$.formwizard.persistence.storageFields['step-' + stepNumber].fields.hasOwnProperty(rowId)) {
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId] = {};
                        }
                        $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId][fieldId] = document.querySelector("#" + formId + " #" + fieldId).value;
                    } else {
                        //add fields to the local fieldstorage property
                        $.formwizard.persistence.storageFields['step-' + stepNumber].fields[fieldId] = document.querySelector("#" + formId + " #" + fieldId).value;
                    }

                },
                text: function (fieldId) {
                    if ($.formwizard.persistence.storageFields['step-' + stepNumber].stepType == 'tabular') {
                        let rowId = $("#" + formId + " #" + fieldId).closest('div.tabular-row').attr('id');

                        if (!$.formwizard.persistence.storageFields['step-' + stepNumber].fields.hasOwnProperty(rowId)) {
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId] = {};
                        }
                        $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId][fieldId] = document.querySelector("#" + formId + " #" + fieldId).value;
                    } else {
                        //add fields to the local fieldstorage property
                        $.formwizard.persistence.storageFields['step-' + stepNumber].fields[fieldId] = document.querySelector("#" + formId + " #" + fieldId).value;
                    }
                },
                radio: (fieldId) => {
                    let radioList = $("#" + formId + " #" + fieldId).closest('div[role="radiogroup"]').find('input:radio');
                    if ($.formwizard.persistence.storageFields['step-' + stepNumber].stepType == 'tabular') {
                        let rowId = $("#" + formId + " #" + fieldId).closest('div.tabular-row').attr('id');
                        if (!$.formwizard.persistence.storageFields['step-' + stepNumber].fields.hasOwnProperty(rowId)) {
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId] = {};
                        }
                        if (radioList.length) {
                            radioList.each(function (index, element) {
                                //add fields to the local fieldstorage property
                                $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId][element.id] = element.checked;
                            });
                        } else {
                            //add fields to the local fieldstorage property
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId][fieldId] = $("#" + formId + " #" + fieldId).is(":checked");
                        }
                    } else {
                        if (radioList.length) {
                            radioList.each(function (index, element) {
                                //add fields to the local fieldstorage property
                                $.formwizard.persistence.storageFields['step-' + stepNumber].fields[element.id] = element.checked;
                            });
                        } else {
                            //add fields to the local fieldstorage property
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[fieldId] = $("#" + formId + " #" + fieldId).is(":checked");
                        }
                    }



                },
                checkbox: (fieldId) => {
                    let isCheckBoxList = $('#' + formId + " #" + fieldId).attr('name').match(/\[\]$/g);

                    if ($.formwizard.persistence.storageFields['step-' + stepNumber].stepType == 'tabular') {
                        let rowId = $("#" + formId + " #" + fieldId).closest('div.tabular-row').attr('id');
                        if (!$.formwizard.persistence.storageFields['step-' + stepNumber].fields.hasOwnProperty(rowId)) {
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId] = {};
                        }
                        if (isCheckBoxList.length) {
                            let checkboxList = $("input[name='" + $("#" + formId + " #" + fieldId).attr('name') + "']");
                            checkboxList.each(function (index, element) {
                                //add fields to the local fieldstorage property
                                $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId][element.id] = element.checked;
                            });
                        } else {
                            //add fields to the local fieldstorage property
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[rowId][fieldId] = $("#" + formId + " #" + fieldId).is(":checked");
                        }
                    } else {
                        if (isCheckBoxList.length) {
                            let checkboxList = $("input[name='" + $("#" + formId + " #" + fieldId).attr('name') + "']");
                            checkboxList.each(function (index, element) {
                                //add fields to the local fieldstorage property
                                $.formwizard.persistence.storageFields['step-' + stepNumber].fields[element.id] = element.checked;
                            });
                        } else {
                            //add fields to the local fieldstorage property
                            $.formwizard.persistence.storageFields['step-' + stepNumber].fields[fieldId] = $("#" + formId + " #" + fieldId).is(":checked");
                        }
                    }

                }
            };

            // save the complete json form inputs object to the local variable
            fieldTypes.hasOwnProperty(fieldType) && fieldTypes[fieldType].call(this, fieldId);

            //save the complete fields json to localstorage 
            localStorage.setItem('formwizard.' + formId, JSON.stringify($.formwizard.persistence.storageFields));
        },
        clearStorage: () => {
            //the prefix for the storage
            let prefix = "formwizard.";

            //iterate all items in the storage
            for (var key in localStorage) {

                //match the prefix text
                if (key.indexOf(prefix) == 0) {
                    localStorage.removeItem(key);
                }
            }
            //clear storage fields variable
            $.formwizard.persistence.storageFields = {};
        },
        loadForm: (formId) => {
            //load fields stored
            $.formwizard.persistence.storageFields = JSON.parse(localStorage.getItem("formwizard." + formId));

            let storageFields = $.formwizard.persistence.storageFields;
            let fieldTypes = {
                'select-one': (fieldId, value) => {
                    let field = document.querySelector("#" + formId + " #" + fieldId);

                    // restore value
                    field.value = value;

                    //trigger change event for select2
                    $("#" + fieldId).trigger('change');

                    //trigger the afterRestoreEvent
                    $.formwizard.triggerEvent('formwizard.' + formId + '.afterRestore', "#" + formId + " #" + fieldId, {
                        fieldId: fieldId,
                        fieldValue: value
                    });
                },
                text: function (fieldId, value) {
                    let field = document.querySelector("#" + formId + " #" + fieldId);

                    // restore value
                    field.value = value;

                    //trigger the afterRestoreEvent
                    $.formwizard.triggerEvent('formwizard.' + formId + '.afterRestore', "#" + formId + " #" + fieldId, {
                        fieldId: fieldId,
                        fieldValue: value
                    });
                },
                radio: (fieldId, value) => {
                    let field = document.querySelector("#" + formId + " #" + fieldId);

                    // restore value
                    field.checked = value;

                    //trigger the afterRestoreEvent
                    $.formwizard.triggerEvent('formwizard.' + formId + '.afterRestore', "#" + formId + " #" + fieldId, {
                        fieldId: fieldId,
                        fieldValue: value
                    });
                },
                checkbox: (fieldId, value) => {
                    let field = document.querySelector("#" + formId + " #" + fieldId);

                    // restore value
                    field.checked = value;

                    //trigger the afterRestoreEvent
                    $.formwizard.triggerEvent('formwizard.' + formId + '.afterRestore', "#" + formId + " #" + fieldId, {
                        fieldId: fieldId,
                        fieldValue: value
                    });
                }
            };

            //iterate an retore data for all the fields
            for (let steps in storageFields) {
                let stepData = storageFields[steps];

                if (stepData.stepType !== 'tabular') {
                    let fields = stepData.fields;

                    for (let id in fields) {
                        if (fields.hasOwnProperty(id)) {
                            let value = fields[id];

                            //get the field type
                            let fieldType = $("#" + id).get(0).type;

                            //cal the relative method to restore the value
                            fieldTypes.hasOwnProperty(fieldType) && fieldTypes[fieldType].call(this, id, value);
                        }
                    }
                } else {
                    let rows = stepData.fields;

                    //get the rows length
                    let rowsLength = Object.keys(rows).length;

                    //trigger click for add row if more than 1
                    if (rowsLength > 1) {
                        for (let iter = 1; iter <= rowsLength - 1; iter++) {
                            //trigger the click for the Add Row button
                            $("#" + steps + " .add_row").trigger('click');
                        }
                    }

                    //iterate the rows
                    for (let row in rows) {

                        if (rows.hasOwnProperty(row)) {
                            let fields = rows[row];

                            //iterate the fields
                            for (let id in fields) {

                                let value = fields[id];

                                //get the field type
                                let fieldType = $("#" + id).get(0).type;

                                //cal the relative method to restore the value
                                fieldTypes.hasOwnProperty(fieldType) && fieldTypes[fieldType].call(this, id, value);
                            }
                        }
                    }
                }
            }

        },
        init: (formId) => {

            //bind the onchange for the form inputs to update the form data as soon it is updated
            $(document).on("change", "#" + formId + " :input", function (e) {
                let stepData = $(this).closest("div.step-content").data('step');
                $.formwizard.persistence.savefield(e.currentTarget, formId, stepData);
            });

            //bind restore button
            $("#" + formId + " button.formwizard_restore").on("click", function (e) {
                e.preventDefault();
                $.formwizard.persistence.loadForm(formId);
            });

        }
    }
};

//used for the class difference to remove the classes
Array.prototype.classDiff = function (a) {
    return this.filter(function (i) {
        return a.indexOf(i) < 0;
    });
};

//removes all the classes from the element other than the specified
$.fn.removeClassesExceptThese = function (classList) {
    /* pass mutliple class name in array like ["first", "second"] */
    var $elem = $(this);

    if ($elem.length > 0) {
        var existingClassList = $elem.attr("class").split(" ");
        var classListToRemove = existingClassList.classDiff(classList);
        $elem
            .removeClass(classListToRemove.join(" "))
            .addClass(classList.join(" "));
    }
    return $elem;
};

Array.prototype.diff = function (arr2) {
    var ret = [];
    for (var i in this) {
        if (this.hasOwnProperty(i)) {
            if (arr2.hasOwnProperty(this[i]) && arr2[this[i]].length > 0) {
                ret.push(this[i]);
            }
        }
    }
    return ret;
};
