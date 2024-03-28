{**
 * templates/controllers/grid/user/reviewer/form/advancedSearchReviewerForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Advanced Search and assignment reviewer form.
 *
 *}
<script type="text/javascript">
	$(function() {
		// Handle moving the reviewer ID from the grid to the second form
		$('#advancedReviewerSearch').pkpHandler('$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler');

		if($('.author_row').length > 4) {
			$("#showAllReviewers").toggleClass('pkp_helpers_display_none');
		}

		$('#showAllReviewers').click(function() {
			$('.pkp_list_box').toggleClass('expandable');
			$(this).toggleClass('pkp_helpers_display_none');
			$("#showLessReviewers").toggleClass('pkp_helpers_display_none');
		});
		$('#showLessReviewers').click(function() {
			$('.pkp_list_box').toggleClass('expandable');
			$(this).toggleClass('pkp_helpers_display_none');
			$("#showAllReviewers").toggleClass('pkp_helpers_display_none');
		})

		let reviewerListData = {$reviewerListData|@json_encode};
		console.log(reviewerListData);
		
		// let authorAffiliations = [];
		// $('.author_affiliation').each(function(key, elem) {
		// 	authorAffiliations.push($(elem).text());
		// });

		// $('.listPanel__item--reviewer__affiliation').each(function(key, elem) {
		// 	let textContent = $(elem).text();
		// 	let cleanText = textContent.replace(/https?:\/\/[^\s]+|\[|\]/g, '').trim()
		// 	if(cleanText !== "") {
		// 		if(authorAffiliations.includes(cleanText)) {
		// 			let badge = "<span class='pkpBadge pkp_helpers_text_warn'>{translate key='reviewer.list.reviewerSameInstitution'}</span>";
		// 			$(elem).parent().parent().children('.listPanel__item--reviewer__brief').each(function(key, elem) {
		// 				$(elem).append(badge);
		// 			});
		// 		}
		// 	}
		// });
	});
</script>
<style>
	.pkp_list_box {
		border: 1px solid #ddd;
		/* margin-bottom: 1rem; */
		padding: 0.3rem 1rem;
		border-radius: 2px;
	}

	.expandable {
		max-height: 7rem;
		overflow-y: scroll;
	}
</style>
<div id="advancedReviewerSearch" class="pkp_form pkp_form_advancedReviewerSearch">
	<div class="section">
		<h3>{translate key="submission.author.list"}</h3>
		<div class="pkp_list_box expandable pkpFormField__description">
            {foreach from=$authors item=affiliation key=name}
				<div class="author_row">
					<span>{$name}</span>
                    {if $affiliation !== ''}
						<span> - </span>
						<span class="author_affiliation">{$affiliation}</span>
                    {/if}
				</div>
            {/foreach}
		</div>
		<button id="showAllReviewers" class="pkpButton pkp_helpers_align_right pkp_helpers_display_none">{translate key="showMore"}</button>
		<button id="showLessReviewers" class="pkpButton pkp_helpers_align_right pkp_helpers_display_none">{translate key="showLess"}</button>
	</div>

	<div class="pkp_uploader_loading">
		<h3>{translate key="editor.submission.suggestedReviewers.title"}</h3>

		{if $enableIncludedReviewers}
			<div class="pkp_notification">
				{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId="reviewersToInclude"
					notificationStyleClass="notifyInfo" notificationContents=$includedReviewers
					notificationTitle="{translate key="editor.submission.includedReviewers.title"}"}
			</div>
		{/if}

		{if $enableExcludedReviewers}
			<div class="pkp_notification">
				{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId="reviewersToExclude"
					notificationStyleClass="notifyInfo" notificationContents=$excludedReviewers
					notificationTitle="{translate key="editor.submission.excludedReviewers.title"}"}
			</div>
		{/if}

			<div class="pkp_notification">
				{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId="reviewersToRecommend"
					notificationStyleClass="notifyInfo" notificationContents=$recommendedReviewers
					notificationTitle="{translate key="editor.submission.recommendedReviewers.title"}"}
			</div>


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
					{foreach from=$reviewerActions item=action}
						{if $action->getId() == 'advancedSearch'}
							{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
						{/if}
					{/foreach}
				</span>
			</div>
		</div>

		{include file="controllers/grid/users/reviewer/form/advancedSearchReviewerAssignmentForm.tpl"}
	</div>
</div>
