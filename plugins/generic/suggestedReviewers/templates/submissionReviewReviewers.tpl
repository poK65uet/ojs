{** * plugins/generic/suggestedReviewers/templates/submissionReviewReviewers.tpl
* * Copyright (c) 2014-2023 Simon Fraser University * Copyright (c) 2003-2023
John Willinsky * Distributed under the GNU GPL v3. For full terms see the file
docs/COPYING. * * Settings form for the pluginTemplate plugin. *}

<div class="submissionWizard__reviewPanel">
  <div class="submissionWizard__reviewPanel__header">
    <h3 id="review-suggested-reviewers">
      {translate key="manager.submissions.suggestedReviewers.title"}
    </h3>
    <pkp-button
      aria-describedby="review-suggested-reviewers"
      class="submissionWizard__reviewPanel__edit"
      @click="openStep('{$step.id}')"
    >
      {translate key="common.edit"}
    </pkp-button>
  </div>
  <div
    class="submissionWizard__reviewPanel__body submissionWizard__reviewPanel__body--editors"
  >
    {if $enableIncludedReviewers}
    <div class="submissionWizard__reviewPanel__item">
      <h4 class="submissionWizard__reviewPanel__item__header">
        {translate key="manager.submissions.suggestedReviewers.included.label"}
      </h4>
      <div class="submissionWizard__reviewPanel__item__value includedReviewers">
        {$includedReviewers}
      </div>
    </div>
    {/if} {if $enableExcludedReviewers}
    <div class="submissionWizard__reviewPanel__item">
      <h4 class="submissionWizard__reviewPanel__item__header">
        {translate key="manager.submissions.suggestedReviewers.excluded.label"}
      </h4>
      <div class="submissionWizard__reviewPanel__item__value excludedReviewers">
        {$excludedReviewers}
      </div>
    </div>
    {/if}
  </div>
</div>
