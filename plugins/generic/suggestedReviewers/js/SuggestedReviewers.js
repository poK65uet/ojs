$(document).ready(function () {
	let incRev = $(".included-reviewers-wrapper > div > textarea");
	incRev.on("change", function () {
		setData("includedReviewers", incRev.val());
	});
	let excRev = $(".excluded-reviewers-wrapper > div > textarea");
	excRev.on("change", function () {
		setData("excludedReviewers", excRev.val());
	});

	let timeout;

	incRev.on("keyup", function () {
		clearTimeout(timeout);
		timeout = setTimeout(function () {
			setData("includedReviewers", incRev.val());
		}, 500);
	});

	excRev.on("keyup", function () {
		clearTimeout(timeout);
		timeout = setTimeout(function () {
			setData("excludedReviewers", excRev.val());
		}, 500);
	});

	function setData(name, val) {
		$(`.${name}`).text(val);
	}
});
