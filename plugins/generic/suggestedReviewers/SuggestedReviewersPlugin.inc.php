<?php

/**
 * @file plugins/generic/compmetingInterests/SuggestedReviewersPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SuggestedReviewersPlugin
 * @ingroup plugins_generic_SuggestedReviewersPlugin
 *
 * @brief SuggestedReviewers plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');

use APP\core\Application;
use APP\facades\Repo;
use APP\pages\submission\SubmissionHandler;
use APP\template\TemplateManager;
use PKP\components\forms\FieldOptions;
use PKP\plugins\Hook;
use PKP\facades\Locale;
use PKP\security\Role;

class SuggestedReviewersPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            // // Get the plugin DAO
            // $this->import('SuggestedReviewersDAO');
            // $suggestedReviewersDao = new SuggestedReviewersDAO();
            // DAORegistry::registerDAO('SuggestedReviewersDAO', $suggestedReviewersDao);

            Hook::add('TemplateManager::display', [$this, 'addToSubmissionWizardSteps']);
            Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'addToSubmissionWizardReviewTemplate']);

            // Override OJS templates
            Hook::add('TemplateResource::getFilename', [$this, '_overridePluginTemplates']);

            // Hooks for Submission form step 3
            Hook::add('Schema::get::publication', [$this, 'addToPublicationSchema']);

            // Hook for Submission Workflow Add Reviewer
            Hook::add('advancedsearchreviewerform::display', [$this, 'loadTemplateData']);

            // Hooks for Workflow Settings Reviews Tab
            Hook::add('Schema::get::context', [$this, 'addToContextSchema']);
            Hook::add('Form::config::before', [$this, 'addToForm']);

            // Tracking sending emails (use listener for 3.4, hook removed)
            Hook::add('Mail::send', [$this, 'sendMail']);

        }
        return $success;
    }

    /****************/
    /**** Plugin ****/
    /****************/

    /**
     * @copydoc Plugin::isSitePlugin()
     */
    function isSitePlugin()
    {
        // This is a site-wide plugin.
        return true;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     * Get the plugin name
     */
    function getDisplayName()
    {
        return __('plugins.generic.suggestedReviewers.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     * Get the description
     */
    function getDescription()
    {
        return __('plugins.generic.suggestedReviewers.description');
    }

    /**
     * @copydoc Plugin::getInstallSitePluginSettingsFile()
     * get the plugin settings
     */
    function getInstallSitePluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Return the location of the plugin's CSS file
     *
     * @return string
     */
    function getStyleSheet()
    {
        return $this->getPluginPath() . '/css/suggestedReviewers.css';
    }

    /**********************************************/
    /**** Workflow > Settings > Review > Setup ****/
    /**********************************************/

    /**
     * Extend the Publication entity's schema with an suggestedReviewers property
     * @param $hookName string
     * @param $args array
     *
     */
    public function addToContextSchema($hookName, $args)
    {

        $schema = $args[0];

        $schema->properties->enableSuggestedRevieweres = new stdClass();
        $schema->properties->enableSuggestedRevieweres->type = 'array';
        $schema->properties->enableSuggestedRevieweres->items = new stdClass();
        $schema->properties->enableSuggestedRevieweres->items->type = 'string';
        $schema->properties->enableSuggestedRevieweres->items->validation = ['in:reviewersToIncluded,reviewersToExcluded'];
    }

    /**
     * Extend Setup form to add suggestedReviewers input field
     * @param $hookName string
     * @param $form object
     *
     */
    public function addtoForm($hookName, $form)
    {

        // Only modify Review Setup form
        if (!defined('FORM_REVIEW_SETUP') || $form->id !== FORM_REVIEW_SETUP) {
            return;
        }

        // get the context
        $request = PKPApplication::get()->getRequest();
        $context = $request->getContext();

        // Add the fields to the form
        $form->addField(
            new FieldOptions('enableSuggestedRevieweres', [
                'label' => __('manager.setup.reviewOptions.suggestedRevieweres.title'),
                'description' => __('manager.setup.reviewOptions.suggestedRevieweres.description'),
                'options' => [
                    [
                        'value' => 'reviewersToIncluded',
                        'label' => __('manager.setup.reviewOptions.suggestedRevieweres.reviewersToIncluded.label')
                    ],
                    [
                        'value' => 'reviewersToExcluded',
                        'label' => __('manager.setup.reviewOptions.suggestedRevieweres.reviewersToExcluded.label')
                    ],
                ],
                'value' => $context->getData('enableSuggestedRevieweres') ? $context->getData('enableSuggestedRevieweres') : [],
            ]),
            array(FIELD_POSITION_BEFORE, 'defaultReviewMode')
        );
    }

    /**
     * Extend the publication entity's schema with includedReviewers and excludedReviewers and recommendedReviewers properties
     * @param $hookName string
     * @param $args array
     *
     */
    public function addToPublicationSchema($hookName, $args)
    {
        $schema = $args[0];

        $schema->properties->recommendedReviewers = new stdClass();
        $schema->properties->recommendedReviewers->type = 'string';
        $schema->properties->recommendedReviewers->multilingual = true;
        $schema->properties->recommendedReviewers->validation = ['nullable'];

        $schema->properties->includedReviewers = new stdClass();
        $schema->properties->includedReviewers->type = 'string';
        $schema->properties->includedReviewers->multilingual = true;
        $schema->properties->includedReviewers->validation = ['nullable'];

        $schema->properties->excludedReviewers = new stdClass();
        $schema->properties->excludedReviewers->type = 'string';
        $schema->properties->excludedReviewers->multilingual = true;
        $schema->properties->excludedReviewers->validation = ['nullable'];
    }

    /**
     * Insert template to review the suggested reviewers data in the submission wizard
     * before completing the submission
     */
    function addToSubmissionWizardReviewTemplate($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $journalId = $request->getContext()->getId();
        $enableSuggestedReviewers = $this->suggestedReviewersEnabled($journalId);
        $enableIncludedReviewers = $this->includedReviewersEnabled($enableSuggestedReviewers);
        $enableExcludedReviewers = $this->excludedReviewersEnabled($enableSuggestedReviewers);

        if ($enableIncludedReviewers || $enableExcludedReviewers) {
            $submission = $params[0]['submission']; /** @var Submission $submission */
            $publication = $submission->getCurrentPublication();
            $includedReviewers = $publication->getLocalizedData('includedReviewers');
            $excludedReviewers = $publication->getLocalizedData('excludedReviewers');

            $step = $params[0]['step']; /** @var string $step */
            $templateMgr = $params[1]; /** @var TemplateManager $templateMgr */
            $templateMgr->assign([
                'includedReviewers' => $includedReviewers,
                'excludedReviewers' => $excludedReviewers,
                'enableIncludedReviewers' => $enableIncludedReviewers,
                'enableExcludedReviewers' => $enableExcludedReviewers
            ]);
            $output =& $params[2];

            if ($step === 'editors') {
                $output .= $templateMgr->fetch($this->getTemplateResource('submissionReviewReviewers.tpl'));
            }

            return false;
        }
    }

    /**
     * Add suggested reviewers section to the details step of the submission wizard
     */
    function addToSubmissionWizardSteps($hookName, $params)
    {
        $request = Application::get()->getRequest();

        if ($request->getRequestedPage() !== 'submission') {
            return;
        }

        if ($request->getRequestedOp() === 'saved') {
            return;
        }

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) {
            return;
        }

        $journalId = $request->getContext()->getId();
        $enableSuggestedReviewers = $this->suggestedReviewersEnabled($journalId);
        $enableIncludedReviewers = $this->includedReviewersEnabled($enableSuggestedReviewers);
        $enableExcludedReviewers = $this->excludedReviewersEnabled($enableSuggestedReviewers);

        if ($enableIncludedReviewers || $enableExcludedReviewers) {
            /** @var TemplateManager $templateMgr */
            $templateMgr = $params[0];
            $publication = $submission->getCurrentPublication();
            $includedReviewers = $publication->getData('includedReviewers');
            $excludedReviewers = $publication->getData('excludedReviewers');
            $primaryLocale = Locale::getLocale();

            $fields = [];
            if ($enableIncludedReviewers) {
                $fields[] = [
                    'name' => "includedReviewers[$primaryLocale]",
                    'component' => 'field-textarea',
                    'class' => 'recommended-reviewers-wrapper',
                    'label' => __('manager.submissions.suggestedReviewers.included.label'),
                    'groupId' => 'default',
                    'isRequired' => false,
                    'isMultilingual' => false,
                    'value' => $includedReviewers ? implode('', $includedReviewers) : null,
                    'size' => 'small',
                ];
            }

            if ($enableExcludedReviewers) {
                $fields[] = [
                    'name' => "excludedReviewers[$primaryLocale]",
                    'component' => 'field-textarea',
                    'class' => 'excluded-reviewers-wrapper',
                    'label' => __('manager.submissions.suggestedReviewers.excluded.label'),
                    'groupId' => 'default',
                    'isRequired' => false,
                    'isMultilingual' => false,
                    'value' => $excludedReviewers ? implode('', $excludedReviewers) : null,
                    'size' => 'small'
                ];
            }

            $action = $request->getIndexUrl() . '/' . $request->getContext()->getPath() . '/api/v1/submissions/' . $submission->getId() . '/publications/' . $publication->getId();
            $steps = $templateMgr->getState('steps');
            $steps = array_map(function ($step) use ($fields, $action, $primaryLocale) {
                if ($step['id'] === 'editors') {
                    $locales = Locale::getSupportedFormLocales();
                    $localesFormatted[] = [
                        'key' => $primaryLocale,
                        'label' => $locales[$primaryLocale],
                    ];
                    $otherLocaleKeys = [];
                    foreach ($locales as $key => $locale) {
                        if ($key !== $primaryLocale) {
                            $localesFormatted[] = [
                                'key' => $key,
                                'label' => $locale,
                            ];
                            $otherLocaleKeys[$key] = '';
                        }
                    }

                    $step['sections'][] = [
                        'id' => 'suggestedReviewers',
                        'name' => __('manager.submissions.suggestedReviewers.title'),
                        'description' => __('manager.submissions.suggestedReviewers.help'),
                        'type' => SubmissionHandler::SECTION_TYPE_FORM,
                        'form' => [
                            'id' => 'suggestedReviewers',
                            'method' => 'PUT',
                            'action' => $action,
                            'fields' => $fields,
                            'groups' => [
                                ['id' => 'default', 'pageId' => 'default'],
                            ],
                            'hiddenFields' => [],
                            'pages' => [
                                ['id' => 'default'],
                            ],
                            'primaryLocale' => $primaryLocale,
                            'visibleLocales' => [$primaryLocale],
                            'supportedFormLocales' => $localesFormatted,
                            'errors' => [],
                        ],
                    ];
                }
                return $step;
            }, $steps);

            $templateMgr->setState([
                'steps' => $steps,
            ]);

            $templateMgr->addJavaScript(
                'suggested-reviewers',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/SuggestedReviewers.js',
                [
                    'contexts' => 'backend',
                    'priority' => TemplateManager::STYLE_SEQUENCE_LATE,
                ]
            );

            $templateMgr->addStyleSheet(
                'suggestedReviewers',
                $request->getBaseUrl() . '/' . $this->getStyleSheet(),
                [
                    'contexts' => ['backend']
                ]
            );
        }

        return false;
    }

    protected function suggestedReviewersEnabled(int $journalId): array
    {
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journal = $journalDao->getById($journalId);
        return ($journal) ? $journal->getData('enableSuggestedRevieweres') : [];
    }

    protected function includedReviewersEnabled($enableSuggestedReviewers): bool
    {
        return $enableSuggestedReviewers && in_array('reviewersToIncluded', $enableSuggestedReviewers);
    }

    protected function excludedReviewersEnabled($enableSuggestedReviewers): bool
    {
        return $enableSuggestedReviewers && in_array('reviewersToExcluded', $enableSuggestedReviewers);
    }

    /**
     * Add data to email templates.
     *
     * @param string $hookname
     * @param array $args
     *
     */
    public function sendMail($hookName, $args)
    {
        $form = &$args[0];
        $submission = $form->submission;
        if ($submission) {
            $publication = $submission->getCurrentPublication();
            $excludedReviewers = $publication->getLocalizedData('excludedReviewers');
            $includedReviewers = $publication->getLocalizedData('includedReviewers');
            $args[0]->privateParams['{$excludedReviewers}'] = htmlspecialchars($excludedReviewers);
            $args[0]->privateParams['{$includedReviewers}'] = htmlspecialchars($includedReviewers);
        }
    }

    /**
     * Fired when add reviewer pop-up is called in Submissions > Workflow > Review.
     *
     * @param string $hookname
     * @param array $args
     *
     */
    public function loadTemplateData($hookName, $args)
    {
        // get the form
        $request = PKPApplication::get()->getRequest();
        $form =& $args[0];

        // get suggestedReviewers values
        $publication = $form->getSubmission()->getCurrentPublication();

        $journalId = $request->getContext()->getId();
        $enableSuggestedReviewers = $this->suggestedReviewersEnabled($journalId);
        $enableIncludedReviewers = $this->includedReviewersEnabled($enableSuggestedReviewers);
        $enableExcludedReviewers = $this->excludedReviewersEnabled($enableSuggestedReviewers);

        $includedReviewers = $publication->getLocalizedData('includedReviewers');
        $excludedReviewers = $publication->getLocalizedData('excludedReviewers');
        $recommendedReviewers = $this->getRecommendedReviewers($publication);

        if (!$includedReviewers) {
            $includedReviewers = null;
        }
        if (!$excludedReviewers) {
            $excludedReviewers = null;
        }

        if (!$recommendedReviewers) {
            $recommendedReviewers = null;
        }

        //get authors and affiliations
        $authors = [];
        foreach ($publication->getData('authors') as $author) {
            $affiliations = [];
            foreach ($author->getAffiliation(null) as $affiliationName) {
                $affiliations[] = $affiliationName;
            }

            $authors[$author->getFullName()] = implode(',', array_filter($affiliations));
        }

        $reviewerListData = $this->getReviewers($request);
        // error_log(print_r($reviewerListData, true));

        $templateVars = [
            'enableIncludedReviewers' => $enableIncludedReviewers,
            'enableExcludedReviewers' => $enableExcludedReviewers,
            'includedReviewers' => $includedReviewers,
            'excludedReviewers' => $excludedReviewers,
            'recommendedReviewers' => $recommendedReviewers,
            'authors' => $authors,
            'reviewerListData' => $reviewerListData,
        ];

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign($templateVars);
        // $templateMgr->addStyleSheet(
        //     'suggestedReviewers',
        //     $request->getBaseUrl() . '/' . $this->getStyleSheet(),
        //     [
        //         'contexts' => ['backend']
        //     ]
        // );
    }


    public function getRecommendedReviewers($publication)
    {
        $title = $publication->getLocalizedData('title');
        $abstract = $publication->getLocalizedData('abstract');
        $data = json_encode([
            'title' => $title,
            'abstract' => $abstract,
        ]);

        $url = 'http://127.0.0.1:5000/api/recommendation/';

        $option = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => $data
            ]
        ];

        $context = stream_context_create($option);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $recommendedReviewers = null;
        } else {
            $data = json_decode($response, true);
            $result = $data['result'];
            $recommendedReviewers = array();

            for ($i = 0; $i < count($result); $i++) {
                $recommendedReviewers[] = $result[$i]['name'];
            }

            $recommendedReviewers = implode("\n", $recommendedReviewers);
            $recommendedReviewers = nl2br($recommendedReviewers);
        }

        return $recommendedReviewers;
    }

    public function getReviewers($request)
    {
        $context = $request->getContext();
        // $getReviewers = $request->getDispatcher()->url(
        //     $request,
        //     PKPApplication::ROUTE_API,
        //     $context->getPath(),
        //     'users/reviewers'
        // );

        $reviewers = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
            ->includeReviewerData()
            ->getMany();

        $result = array();
        foreach ($reviewers as $reviewer) {
            $reviewerId = $reviewer->getId();
            error_log(print_r($reviewerId, true));
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');/** @var \PKP\submission\reviewAssignment\ReviewAssignmentDAO $reviewAssignmentDao */
            $reviewAssignment = $reviewAssignmentDao->getById($reviewerId);
            $result[] = [
                'id' => $reviewerId,
                'name' => $reviewer->getFullName(),
                'reviewAssignment' => $reviewAssignment
            ];
        }
        return $result;
    }
}

