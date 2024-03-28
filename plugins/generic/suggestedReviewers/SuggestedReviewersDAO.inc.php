<?php

/**
 * @file SuggestedReviewersDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SuggestedReviewersDAO
 * @ingroup journal
 * @see Publication
 *
 * @brief Operations for retrieving and modifying Suggested Reviewers objects.
 */

use PKP\db\DAO;

class SuggestedReviewersDAO extends DAO
{

	/**
	 * replace an existing Suggested Reviewers record.
	 * @param $pubId integer
	 * @param $locale string
	 * @param $name string
	 * @param $value array
	 */
	function replaceSuggestedReviewers($pubId, $locale, $name, $value)
	{
		$updateArr = [
			'publication_id' => $pubId,
			'locale' => $locale,
			'setting_name' => $name,
			'setting_value' => nl2br($value)
		];
		$keys = ['publication_id', 'locale', 'setting_name'];

		$result = $this->replace('publication_settings', $updateArr, $keys);
		return $result;
	}

}
