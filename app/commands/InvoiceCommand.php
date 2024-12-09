<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.12.2019
 * Time: 12:03
 */

namespace App\Console;

use App\Model\IdokladService;
use App\Model\InvoiceService;
use App\Model\UserService;
use malcanek\iDoklad\request\iDokladFilter;
use malcanek\iDoklad\request\iDokladSort;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

final class InvoiceCommand extends BaseCommand {

	/** @var UserService */
	private $userService;

	/** @var InvoiceService */
	private $invoiceService;

	/** @var IdokladService */
	private $iDokladService;

	/**
	 * InvoiceCommand constructor.
	 * @param UserService $userService
	 * @param InvoiceService $invoiceService
	 * @param IdokladService $iDokladService
	 */
	public function __construct(UserService $userService, InvoiceService $invoiceService, IdokladService $iDokladService) {
		parent::__construct();
		$this->userService = $userService;
		$this->invoiceService = $invoiceService;
		$this->iDokladService = $iDokladService;
	}

	protected function configure() {
		$this->setName('cron:idoklad:invoices')
			->setDescription('Sync invoices from iDoklad to database')
			->addArgument('force', InputArgument::OPTIONAL, 'Use "force" to force sync all');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$force = $input->getArgument('force') == 'force';

		$users = $this->userService->getUsers($force ? UserService::DELETED_LEVEL: UserService::MEMBER_LEVEL)
			->fetchPairs('idoklad_id', 'id');
		$invoices = $this->invoiceService->getInvoices()
			->fetchPairs('id', 'date_update');

		$this->iDokladService->authenticate();
		$request = $this->iDokladService->requestsInvoices();

		$request->addFilter(new iDokladFilter('NumericSequenceId', '==', $this->iDokladService->memberNumericSequence));

		//Velikost stránky, v případě force = true se ignoruje
		$request->setPageSize(100);
		$request->addSort(new iDokladSort('DateOfIssue', 'desc'));

		$response = $this->iDokladService->sendRequest($request);
		$data = $force ? $this->iDokladService->getData($request, $response) : $response->getData();

		foreach ($data as $invoice) {
			//Neevidujeme neodeslané faktury
			if (!$invoice['IsSentToPurchaser']) {
				continue;
			}

			//Pokud faktura není pro členy -> přeskočit
			if (!($userId = $users[$invoice['PurchaserId']] ?? NULL)) {
				continue;
			}

			$invoice = $this->iDokladService::createInvoice($invoice);
			$invoice['user_id'] = $userId;

			if ($updateTime = $invoices[$invoice['id']] ?? NULL) {
				if ($updateTime < $invoice['date_update']) {
					$this->invoiceService->updateInvoice($invoice);
					$this->writeln($output,'Update', $invoice['id']);
				} else {
					$this->writeln($output, 'No change', $invoice['id']);
				}
				unset($invoices[$invoice['id']]);
			} else {
				$this->invoiceService->saveInvoice($invoice);
				$this->writeln($output, 'Create', $invoice['id']);
			}
		}

		//Máme nějaké faktury, co nejsou v idokladu -> smazat
		if (($force) and (count($invoices))) {
			foreach ($invoices as $id => $updateTime) {
				$this->writeln($output, 'Delete', $id);
				$this->invoiceService->getInvoiceById($id)
					->delete();
			}
		}

		return 0;
	}
}