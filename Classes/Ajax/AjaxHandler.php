<?php
namespace Tesseract\Dataquery\Ajax;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Http\AjaxRequestHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class answers to AJAX calls from the 'dataquery' extension.
 *
 * @author Fabien Udriot <fabien.udriot@ecodev.ch>
 * @package TYPO3
 * @subpackage tx_dataquery
 */
class AjaxHandler {

	/**
	 * Returns the parsed query through dataquery parser
	 * or error messages from exceptions should any have been thrown
	 * during query parsing.
	 *
	 * @param array $parameters Empty array (yes, that's weird but true)
	 * @param AjaxRequestHandler $ajaxObj Back-reference to the calling object
	 * @return	void
	 */
	public function validate($parameters, AjaxRequestHandler $ajaxObj) {
		$parsingSeverity = FlashMessage::OK;
		$executionSeverity = FlashMessage::OK;
		$executionMessage = '';
		$warningMessage = '';
		/** @var \TYPO3\CMS\Lang\LanguageService $languageService */
		$languageService = $GLOBALS['LANG'];

		// Try parsing and building the query
		try {
			// Get the query to parse from the GET/POST parameters
			$query = GeneralUtility::_GP('query');
			// Create an instance of the parser
			/** @var $parser \Tesseract\Dataquery\Parser\QueryParser */
			$parser = GeneralUtility::makeInstance('Tesseract\\Dataquery\\Parser\\QueryParser');
			// Clean up and prepare the query string
			$query = $parser->prepareQueryString($query);
			// Parse the query
			// NOTE: if the parsing fails, an exception will be received, which is handled further down
			// The parser may return a warning, though
			$warningMessage = $parser->parseQuery($query);
			// Build the query
			$parsedQuery = $parser->buildQuery();
			// The query building completed, issue success message
			$parsingTitle = $languageService->sL('LLL:EXT:dataquery/locallang.xml:query.success');
			$parsingMessage = $parsedQuery;

			// Force a LIMIT to 1 and try executing the query
			$parser->getSQLObject()->structure['LIMIT'] = 1;
			// Rebuild the query with the new limit
			$executionQuery = $parser->buildQuery();
			// Execute query and report outcome
			/** @var \TYPO3\CMS\Core\Database\DatabaseConnection $databaseConnection */
			$databaseConnection = $GLOBALS['TYPO3_DB'];
			$res = $databaseConnection->sql_query($executionQuery);
			if ($res === FALSE) {
				$executionSeverity = FlashMessage::ERROR;
				$errorMessage = $databaseConnection->sql_error();
				$executionMessage = sprintf(
					$languageService->sL('LLL:EXT:dataquery/locallang.xml:query.executionFailed'),
					$errorMessage
				);
			} else {
				$executionMessage = $languageService->sL('LLL:EXT:dataquery/locallang.xml:query.executionSuccessful');
			}
		}
		catch(\Exception $e) {
			// The query parsing failed, issue error message
			$parsingSeverity = FlashMessage::ERROR;
			$parsingTitle = $languageService->sL('LLL:EXT:dataquery/locallang.xml:query.failure');
			$exceptionCode = $e->getCode();
			$parsingMessage = $languageService->sL('LLL:EXT:dataquery/locallang.xml:query.exception-' . $exceptionCode);
		}
		// Render parsing result as flash message
		/** @var $flashMessage FlashMessage */
		$flashMessage = GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
			$parsingMessage,
			$parsingTitle,
			$parsingSeverity
		);
		$content = $flashMessage->render();
		// If a warning was returned by the query parser, display it here
		if (!empty($warningMessage)) {
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				$warningMessage,
					$languageService->sL('LLL:EXT:dataquery/locallang.xml:query.warning'),
				FlashMessage::WARNING
			);
			$content .= $flashMessage->render();
		}
		// If the query was also executed, render execution result
		if (!empty($executionMessage)) {
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				$executionMessage,
				'',
				$executionSeverity
			);
			$content .= $flashMessage->render();
		}
		$ajaxObj->addContent('dataquery', $content);
	}
}