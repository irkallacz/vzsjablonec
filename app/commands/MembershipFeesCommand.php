<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 6.1.2018
 * Time: 19:53
 */

namespace App\Console;

use App\Model\UserService;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

/**
 * Class PeoplePresenter
 * @package App\CronModule\presenters
 */
final class MembershipFeesCommand extends BaseCommand
{
	/** @var UserService */
	private $userService;

	/** @var string */
	private $clientId;

	/** @var string */
	private $clientSecret;

	/**
	 * MembershipFeesCommand constructor.
	 * @param UserService $userService
	 * @param string $clientId
	 * @param string $clientSecret
	 */
	public function __construct(UserService $userService, string $clientId, string $clientSecret)
	{
		parent::__construct();

		$this->userService = $userService;
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
	}

	protected function configure()
	{
		$this->setName('cron:membership:fees')
			->setDescription('Add membership fees invoice to accounting');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$now = new DateTime();

		$token = $this->authorize();

		foreach ($this->userService->getUsers(UserService::MEMBER_LEVEL) as $member) {
			if (!$member->iDokladId) {
				continue;
			}

			if (is_null($member->membership_fee)) {
				if ($now->diff($member->date_born)->y >= 18) {
					$amount = 1500;
				} else {
					$amount = 2000;
				}
			} else {
				$amount = $member->membership_fee;
			}

			$name = UserService::getFullName($member);
			$this->writeln($output, $name, $amount);

			if ($member->membership_fee == 0) {
				continue;
			}

			$invoice = $this->createInvoice($member->iDokladId, $name, (int) $now->format('Y'), $amount);
			$invoice = Json::encode($invoice);

			$this->sendInvoice($token, $invoice);
		}
	}

	protected function createInvoice(int $memberId, string $memberName, int $year, float $amount): array
	{
		return [
			'InvoiceTemplate' => [
				'BankAccountId' => 3971353,
				'CurrencyId' => 1,
				'ConstantSymbolId' => 7,
				'DeliveryAddressId' => null,
				'Description' => 'Členské příspěvky za pololetí ' . $year,
				'DiscountPercentage' => 0,
				'DocumentType' => 0,
				'InvoiceMaturity' => 14,
				'IsConstantVariableSymbol' => false,
				'IsDocumentInVatOnPay' => false,
				'IsEet' => false,
				'Items' => [
					[
						'Amount' => 1,
						'ItemType' => 0,
						'Name' => 'Členské příspěvky za pololetí ' . $year,
						'PriceType' => 1,
						'PriceListItemId' => null,
						'UnitPrice' => $amount,
						'Unit' => '',
						'VatCodeId' => null,
						'VatRateType' => 2
					]
				],
				'ItemsTextPrefix' => '',
				'ItemsTextSuffix' => '',
				'Note' => $memberName,
				'NumericSequenceId' => 1249547,
				'OrderNumber' => '',
				'PartnerId' => $memberId,
				'PaymentOptionId' => 1,
				'ReportLanguage' => 1,
				'TaxingType' => 0,
				'VariableSymbol' => '',
				'VatReverseChargeCodeId' => 1
			],
			'RecurringSetting' => [
				'CopyCountEnd' => 2,
				'DateOfStart' => '2023-02-01',
				'DateOfEnd' => null,
				'IssueLastDayOfMonth' => false,
				'RecurrenceCount' => 7,
				'RecurrenceType' => 2,
				'SendToPurchaser' => true,
				'SendToSupplier' => false,
				'SendToAccountant' => false,
				'SendToOtherEmails' => false,
				'TemplateName' => 'Členské příspěvky za pololetí '. $year,
				'TypeOfEnd' => 2
			]
		];
	}

	protected function sendInvoice(string $token, string $json)
	{
		$this->sendRequest('https://api.idoklad.cz/v3/RecurringInvoices', $json,
			[
				'Authorization: Bearer '. $token,
				'Content-Type: application/json'
			]
		);
	}

	protected function authorize(): string
	{
		$response = $this->sendRequest('https://identity.idoklad.cz/server/connect/token',
			http_build_query([
				'grant_type' => 'client_credentials',
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'scope' => 'idoklad_api',
			]),
			[
				'Content-Type: application/x-www-form-urlencoded'
			]
		);

		if (!$response) {
			throw new \Exception('Authorization fail');
		}

		$response = Json::decode($response);

		if (!property_exists($response, 'access_token')) {
			throw new \Exception('Access token not found');
		}

		return $response->access_token;
	}

	protected function sendRequest(string $url, string $body, array $headers)
	{
		$request = curl_init($url);

		curl_setopt($request, CURLOPT_POST, true);
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($request, CURLOPT_TIMEOUT, 0);
		curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);

		curl_setopt($request, CURLOPT_POSTFIELDS, $body);
		curl_setopt($request, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($request);
		curl_close($request);

		return $response;
	}
}