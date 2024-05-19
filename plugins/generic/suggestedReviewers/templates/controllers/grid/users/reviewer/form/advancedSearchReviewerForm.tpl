{** *
templates/controllers/grid/user/reviewer/form/advancedSearchReviewerForm.tpl * *
Copyright (c) 2014-2023 Simon Fraser University * Copyright (c) 2003-2023 John
Willinsky * Distributed under the GNU GPL v3. For full terms see the file
docs/COPYING. * * Settings form for the pluginTemplate plugin. *}

<script type="text/javascript">
  $(function () {
    // Handle moving the reviewer ID from the grid to the second form
    $("#advancedReviewerSearch").pkpHandler(
      "$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler"
    );

    if ($(".author_list").length > 5) {
      $("#showAllReviewers").toggleClass("pkp_helpers_display_none");
    }

    $("#showAllReviewers").click(function () {
      $(".pkp_list_box").toggleClass("expandable");
      $(this).toggleClass("pkp_helpers_display_none");
      $("#showLessReviewers").toggleClass("pkp_helpers_display_none");
    });

    $("#showLessReviewers").click(function () {
      $(".pkp_list_box").toggleClass("expandable");
      $(this).toggleClass("pkp_helpers_display_none");
      $("#showAllReviewers").toggleClass("pkp_helpers_display_none");
    });
  });
</script>
<style>
  .pkp_list_box {
    border: 1px solid #ddd;
    padding: 0.5rem 1rem;
    border-radius: 2px;
  }

  .expandable {
    max-height: 6rem;
    overflow-y: scroll;
  }
</style>
<div
  id="advancedReviewerSearch"
  class="pkp_form pkp_form_advancedReviewerSearch"
>
  <div class="section">
    <h3>{translate key="submission.author.list"}</h3>
    <div class="pkp_list_box expandable pkpFormField__description">
      {foreach from=$authors item=affiliation key=name}
      <div class="author_list">
        <span>{$name}</span>
        {if $affiliation !== ''}
        <span> - </span>
        <span class="author_affiliation">{$affiliation}</span>
        {/if}
      </div>
      {/foreach}
    </div>
    <button
      id="showAllReviewers"
      class="pkpButton pkp_helpers_align_right pkp_helpers_display_none"
    >
      {translate key="showMore"}
    </button>
    <button
      id="showLessReviewers"
      class="pkpButton pkp_helpers_align_right pkp_helpers_display_none"
    >
      {translate key="showLess"}
    </button>
  </div>

  <div class="pkp_uploader_loading">
    <h3>{translate key="editor.submission.suggestedReviewers.title"}</h3>

    {if $enableSuggestedReviewers} {if $enableIncludedReviewers}
    <div class="pkp_notification">
      {include file="controllers/notification/inPlaceNotificationContent.tpl"
      notificationId="reviewersToInclude" notificationStyleClass="notifyInfo"
      notificationContents=$includedReviewers notificationTitle="{translate
      key="editor.submission.includedReviewers.title"}"}
    </div>
    {/if} {if $enableExcludedReviewers}
    <div class="pkp_notification">
      {include file="controllers/notification/inPlaceNotificationContent.tpl"
      notificationId="reviewersToExclude" notificationStyleClass="notifyInfo"
      notificationContents=$excludedReviewers notificationTitle="{translate
      key="editor.submission.excludedReviewers.title"}"}
    </div>
    {/if}

    <div class="pkp_notification">
      {include file="controllers/notification/inPlaceNotificationContent.tpl"
      notificationId="reviewersToRecommend" notificationStyleClass="notifyInfo"
      notificationContents=$recommendedReviewers notificationTitle="{translate
      key="editor.submission.recommendedReviewers.title"}"}
    </div>

    {/if}
  </div>

  <div id="searchGridAndButton">

		{assign var="uuid" value=""|uniqid|escape}
		<div id="select-reviewer-{$uuid}">
			<select-reviewer-list-panel
				v-bind="components.selectReviewer"
				@set="set"
			/>
		</div>
		<script type="text/javascript">
			// let uuid = "<?php echo $uuid; ?>";
			// let selectReviewerListData = <?php echo json_encode($selectReviewerListData); ?>;
			pkp.registry.init('select-reviewer-{$uuid}', 'Container', {$selectReviewerListData|@json_encode});
		</script>

		{** This button will get the reviewer selected in the grid and insert their ID into the form below **}
		{fbvFormSection class="form_buttons"}
			{fbvElement type="button" id="selectReviewerButton" label="editor.submission.selectReviewer"}
			{foreach from=$reviewerActions item=action}
				{if $action->getId() == 'advancedSearch'}
					{continue}
				{/if}
				{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
			{/foreach}
		{/fbvFormSection}
	</div>

  <div id="regularReviewerForm" class="pkp_reviewer_form">
    {** Display the name of the selected reviewer **}
    <div class="selected_reviewer">
      <div class="label">
        {translate key="editor.submission.selectedReviewer"}
      </div>
      <div class="value">
        <span id="selectedReviewerName" class="name"></span>
        <span class="actions">
          {foreach from=$reviewerActions item=action} {if $action->getId() ==
          'advancedSearch'} {include file="linkAction/linkAction.tpl"
          action=$action contextId="createReviewerForm"} {/if} {/foreach}
        </span>
      </div>
    </div>

    {include
    file="controllers/grid/users/reviewer/form/advancedSearchReviewerAssignmentForm.tpl"}
  </div>
</div>
