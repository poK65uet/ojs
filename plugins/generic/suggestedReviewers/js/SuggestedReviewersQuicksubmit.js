$(document).ready(function () {
	let sugRevSection = $(`
		<div id="sug_rev_section">
			<h2>${sugRevData.title}</h2>
			<p>${sugRevData.help}</h2>
		</div>
	`);

	let enableRecRev = $(`
		<div class="section">
			<label for="recommendedReviewers">${sugRevData.recommendedReviewersTitle}</label>
			<textarea
				id="recommendedReviewers"
				name="recommendedReviewers">${sugRevData.recommendedReviewers}</textarea>
		</div>
	`);

	let enableExcRev = $(`
		<div class="section">
			<label for="excludedReviewers">${sugRevData.excludedReviewersTitle}</label>
			<textarea
				id="excludedReviewers"
				name="excludedReviewers">${sugRevData.excludedReviewers}</textarea>
		</div>
	`);

	if(sugRevData.enableRecommendedReviewers || sugRevData.enableExcludedReviewers) {
		$('#tagitFields').append(sugRevSection);
	}

	if(sugRevData.enableRecommendedReviewers) {
		$('#sug_rev_section').append(enableRecRev);
	}

	if(sugRevData.enableExcludedReviewers) {
		$('#sug_rev_section').append(enableExcRev);
	}
});
