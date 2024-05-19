<?php

/**
 * @file SuggestedReviewersPlugin.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SuggestedReviewersPlugin
 *
 * @brief Plugin class for the SuggestedReviewers plugin.
 */

use APP\core\Application;
use APP\pages\submission\SubmissionHandler;
use APP\template\TemplateManager;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class SuggestedReviewersPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            // Add hooks
            Hook::add('TemplateManager::display', $this->addToSubmissionWizardSteps(...));
            Hook::add('Template::SubmissionWizard::Section::Review', $this->addToSubmissionWizardReviewTemplate(...));

            // Override OJS templates
            Hook::add('TemplateResource::getFilename', $this->_overridePluginTemplates(...));

            // Hooks for submission form
            Hook::add('Schema::get::publication', $this->addToPublicationSchema(...));

            // Hook for submission workflow add reviewer
            Hook::add('advancedsearchreviewerform::display', $this->loadTemplateData(...));

            // Hooks for workflow settings reviews Tab
            Hook::add('Schema::get::context', $this->addToContextSchema(...));
            Hook::add('Form::config::before', $this->addToReviewSetupForm(...));

        }
        return $success;
    }

    /**
     * Check if the plugin is a site-wide plugin
     *
     * @return bool
     */
    public function isSitePlugin()
    {
        return true;
    }

    /**
     * Get the plugin name
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.suggestedReviewers.displayName');
    }

    /**
     * Get the plugin description
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.suggestedReviewers.description');
    }

    /**
     * Return the path to the plugin's stylesheet
     *
     * @return string
     */
    public function getStyleSheet()
    {
        return $this->getPluginPath() . '/css/suggestedReviewers.css';
    }

    /**
     * Extend the Publication entity's schema with an suggestedReviewers property
     *
     * @param $hookName string
     * @param $args array
     */
    public function addToContextSchema(string $hookName, array $args)
    {
        $schema = $args[0];
        $schema->properties->enableSuggestedRevieweres = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'string',
                'validation' => ['in:reviewersToIncluded,reviewersToExcluded']
            ]
        ];
    }

    /**
     * Add suggestedReviewers input field to the review setup form
     *
     * @param $hookName string
     * @param $form object
     */
    public function addToReviewSetupForm(string $hookName, FormComponent $form)
    {

        // Check if the form is the review setup form
        if (!defined('FORM_REVIEW_SETUP') || $form->id !== FORM_REVIEW_SETUP) {
            return;
        }

        // Get the context
        $context = Application::get()->getRequest()->getContext();

        if (!$context) {
            return;
        }

        // Add the suggestedReviewers field to the form
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
            [FIELD_POSITION_BEFORE, 'defaultReviewMode']
        );
    }

    /**
     * Add plugin properties to the publication schema
     *
     * @param $hookName string
     * @param $args array
     */
    public function addToPublicationSchema($hookName, $args)
    {
        $schema = $args[0];

        $schema->properties->includedReviewers = (object) [
            'type' => 'string',
            'multilingual' => true,
            'validation' => ['nullable']
        ];

        $schema->properties->excludedReviewers = (object) [
            'type' => 'string',
            'multilingual' => true,
            'validation' => ['nullable']
        ];

        // $schema->properties->recommendedReviewers = (object) [
        //     'type' => 'string',
        //     'multilingual' => true,
        //     'validation' => ['nullable']
        // ];
    }

    /**
     * Add template to the suggested reviewers data in the submission wizard
     * before completing the submission
     */
    public function addToSubmissionWizardReviewTemplate($hookName, $params)
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
            $output = & $params[2];

            if ($step === 'editors') {
                $output .= $templateMgr->fetch($this->getTemplateResource('submissionReviewReviewers.tpl'));
            }

            return false;
        }
    }

    /**
     * Add suggested reviewers section to the details step of the submission wizard
     */
    public function addToSubmissionWizardSteps($hookName, $params)
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
                    'name' => "includedReviewers[{$primaryLocale}]",
                    'component' => 'field-textarea',
                    'class' => 'included-reviewers-wrapper',
                    'label' => __('manager.submissions.suggestedReviewers.included.label'),
                    'groupId' => 'default',
                    'value' => $includedReviewers ? implode('', $includedReviewers) : null,
                    'size' => 'small',
                ];
            }

            if ($enableExcludedReviewers) {
                $fields[] = [
                    'name' => "excludedReviewers[{$primaryLocale}]",
                    'component' => 'field-textarea',
                    'class' => 'excluded-reviewers-wrapper',
                    'label' => __('manager.submissions.suggestedReviewers.excluded.label'),
                    'groupId' => 'default',
                    'value' => $excludedReviewers ? implode('', $excludedReviewers) : null,
                    'size' => 'small'
                ];
            }

            $action = $request->getIndexUrl() . '/' . $request->getContext()->getPath() . '/api/v1/submissions/' . $submission->getId() . '/publications/' . $publication->getId();
            $steps = $templateMgr->getState('steps');
            // error_log(print_r($steps, true));
            $steps = array_map(function ($step) use ($fields, $action, $primaryLocale) {
                if ($step['id'] === 'editors') {
                    $locales = Locale::getSupportedFormLocales();
                    $localesFormatted[] = [
                        'key' => $primaryLocale,
                        'label' => $locales[$primaryLocale],
                    ];
                    foreach ($locales as $key => $locale) {
                        if ($key !== $primaryLocale) {
                            $localesFormatted[] = [
                                'key' => $key,
                                'label' => $locale,
                            ];
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
                'suggestedReviewers',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/suggestedReviewers.js',
                [
                    'contexts' => ['backend'],
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
     * Override plugin templates Submissions > Workflow > Review
     *
     * @param array $args
     *
     */
    public function loadTemplateData($hookName, $args)
    {
        // get the form
        $request = Application::get()->getRequest();
        $form = & $args[0];

        // get suggestedReviewers values
        $publication = $form->getSubmission()->getCurrentPublication();

        $journalId = $request->getContext()->getId();

        $enableSuggestedReviewers = $this->suggestedReviewersEnabled($journalId);

        $enableIncludedReviewers = $this->includedReviewersEnabled($enableSuggestedReviewers);
        $enableExcludedReviewers = $this->excludedReviewersEnabled($enableSuggestedReviewers);

        $includedReviewers = $publication->getLocalizedData('includedReviewers');
        $excludedReviewers = $publication->getLocalizedData('excludedReviewers');

        $recommendedReviewers = $enableSuggestedReviewers ? $this->getRecommendedReviewers($publication) : null;

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
            foreach ($author->getAffiliation(null) as $affiliation) {
                $affiliations[] = $affiliation;
            }
            $authors[$author->getFullName()] = implode(',', array_filter($affiliations));
        }

        $templateVars = [
            'enableSuggestedReviewers' => $enableSuggestedReviewers,
            'enableIncludedReviewers' => $enableIncludedReviewers,
            'enableExcludedReviewers' => $enableExcludedReviewers,
            'includedReviewers' => $includedReviewers,
            'excludedReviewers' => $excludedReviewers,
            'recommendedReviewers' => $recommendedReviewers,
            'authors' => $authors,
            // 'reviewerListData' => $reviewerListData,
        ];

        $templateMgr = TemplateManager::getManager($request);
        // error_log("URL: " . $request->getBaseUrl() . '/' . $this->getStyleSheet());
        // $templateMgr->addStyleSheet(
        //     'suggestedReviewers',
        //     $request->getBaseUrl() . '/' . $this->getStyleSheet(),
        //     [
        //         'contexts' => ['backend']
        //     ]
        // );
        $templateMgr->assign($templateVars);
    }


    /**
     * Get the recommended reviewers from the recommendation API
     *
     * @param object $publication
     */
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
            $recommendedReviewers = [];

            for ($i = 0; $i < count($result); $i++) {
                $recommendedReviewers[] = $result[$i]['name'];
            }

            $recommendedReviewers = implode("\n", $recommendedReviewers);
            $recommendedReviewers = nl2br($recommendedReviewers);
        }

        return $recommendedReviewers;
    }

    // public function getReviewers($request)
    // {
    //     $context = $request->getContext();
    //     // $getReviewers = $request->getDispatcher()->url(
    //     //     $request,
    //     //     PKPApplication::ROUTE_API,
    //     //     $context->getPath(),
    //     //     'users/reviewers'
    //     // );

    //     $reviewers = Repo::user()->getCollector()
    //         ->filterByContextIds([$context->getId()])
    //         ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
    //         ->includeReviewerData()
    //         ->getMany();

    //     $result = array();
    //     foreach ($reviewers as $reviewer) {
    //         $reviewerId = $reviewer->getId();
    //         error_log(print_r($reviewerId, true));
    //         $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');/** @var \PKP\submission\reviewAssignment\ReviewAssignmentDAO $reviewAssignmentDao */
    //         $reviewAssignment = $reviewAssignmentDao->getById($reviewerId);
    //         $result[] = [
    //             'id' => $reviewerId,
    //             'name' => $reviewer->getFullName(),
    //             'reviewAssignment' => $reviewAssignment
    //         ];
    //     }
    //     return $result;
    // }
}
