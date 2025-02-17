<?php
/**
 * GroupUpdateService.php
 * Copyright (c) 2019 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Services\Internal\Update;

use FireflyIII\Exceptions\DuplicateTransactionException;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Factory\TransactionJournalFactory;
use FireflyIII\Models\TransactionGroup;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Services\Internal\Destroy\JournalDestroyService;
use Log;

/**
 * Class GroupUpdateService
 */
class GroupUpdateService
{
    /**
     * Update a transaction group.
     *
     * @param TransactionGroup $transactionGroup
     * @param array            $data
     *
     * @return TransactionGroup
     * @throws DuplicateTransactionException
     * @throws FireflyException
     */
    public function update(TransactionGroup $transactionGroup, array $data): TransactionGroup
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        Log::debug('Now in group update service', $data);
        /** @var array $transactions */
        $transactions = $data['transactions'] ?? [];
        // update group name.
        if (array_key_exists('group_title', $data)) {
            Log::debug(sprintf('Update transaction group #%d title.', $transactionGroup->id));
            $transactionGroup->title = $data['group_title'];
            $transactionGroup->save();
        }


        if (empty($transactions)) {
            Log::debug('No transactions submitted, do nothing.');

            return $transactionGroup;
        }

        if (1 === count($transactions) && 1 === $transactionGroup->transactionJournals()->count()) {
            /** @var TransactionJournal $first */
            $first = $transactionGroup->transactionJournals()->first();
            Log::debug(sprintf('Will now update journal #%d (only journal in group #%d)', $first->id, $transactionGroup->id));
            $this->updateTransactionJournal($transactionGroup, $first, reset($transactions));
            $transactionGroup->refresh();
            app('preferences')->mark();

            return $transactionGroup;
        }

        Log::debug('Going to update split group.');

        $existing = $transactionGroup->transactionJournals->pluck('id')->toArray();
        $updated  = $this->updateTransactions($transactionGroup, $transactions);
        Log::debug('Array of updated IDs: ', $updated);

        if (0 === count($updated)) {
            Log::error('There were no transactions updated or created. Will not delete anything.');
            $transactionGroup->refresh();
            app('preferences')->mark();
            return $transactionGroup;
        }

        $result = array_diff($existing, $updated);
        Log::debug('Result of DIFF: ', $result);
        if (count($result) > 0) {
            /** @var string $deletedId */
            foreach ($result as $deletedId) {
                /** @var TransactionJournal $journal */
                $journal = $transactionGroup->transactionJournals()->find((int) $deletedId);
                /** @var JournalDestroyService $service */
                $service = app(JournalDestroyService::class);
                $service->destroy($journal);
            }
        }

        app('preferences')->mark();
        $transactionGroup->refresh();

        return $transactionGroup;
    }

    /**
     * Update single journal.
     *
     * @param TransactionGroup   $transactionGroup
     * @param TransactionJournal $journal
     * @param array              $data
     */
    private function updateTransactionJournal(TransactionGroup $transactionGroup, TransactionJournal $journal, array $data): void
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        if (empty($data)) {
            return;
        }
        if (1 === count($data) && array_key_exists('transaction_journal_id', $data)) {
            return;
        }
        /** @var JournalUpdateService $updateService */
        $updateService = app(JournalUpdateService::class);
        $updateService->setTransactionGroup($transactionGroup);
        $updateService->setTransactionJournal($journal);
        $updateService->setData($data);
        $updateService->update();
    }

    /**
     * @param TransactionGroup $transactionGroup
     * @param array            $transactions
     *
     * @return array
     * @throws DuplicateTransactionException
     * @throws FireflyException
     */
    private function updateTransactions(TransactionGroup $transactionGroup, array $transactions): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        // updated or created transaction journals:
        $updated = [];
        /**
         * @var int   $index
         * @var array $transaction
         */
        foreach ($transactions as $index => $transaction) {
            Log::debug(sprintf('Now at #%d of %d', ($index + 1), count($transactions)), $transaction);
            $journalId = (int) ($transaction['transaction_journal_id'] ?? 0);
            /** @var TransactionJournal|null $journal */
            $journal = $transactionGroup->transactionJournals()->find($journalId);
            if (null === $journal) {
                Log::debug('This entry has no existing journal: make a new split.');
                // force the transaction type on the transaction data.
                // by plucking it from another journal in the group:
                if (!array_key_exists('type', $transaction)) {
                    Log::debug('No transaction type is indicated.');
                    /** @var TransactionJournal|null $randomJournal */
                    $randomJournal = $transactionGroup->transactionJournals()->inRandomOrder()->with(['transactionType'])->first();
                    if (null !== $randomJournal) {
                        $transaction['type'] = $randomJournal->transactionType->type;
                        Log::debug(sprintf('Transaction type set to %s.', $transaction['type']));
                    }
                }
                Log::debug('Call createTransactionJournal');
                $newJournal = $this->createTransactionJournal($transactionGroup, $transaction);
                Log::debug('Done calling createTransactionJournal');
                if (null !== $newJournal) {
                    $updated[] = $newJournal->id;
                }
                if (null === $newJournal) {
                    Log::error('createTransactionJournal returned NULL, indicating something went wrong.');
                }
            }
            if (null !== $journal) {
                Log::debug('Call updateTransactionJournal');
                $this->updateTransactionJournal($transactionGroup, $journal, $transaction);
                $updated[] = $journal->id;
                Log::debug('Done calling updateTransactionJournal');
            }
        }

        return $updated;
    }

    /**
     * @param TransactionGroup $transactionGroup
     * @param array            $data
     *
     * @return TransactionJournal|null
     *
     * @throws DuplicateTransactionException
     * @throws FireflyException
     * @throws \JsonException
     */
    private function createTransactionJournal(TransactionGroup $transactionGroup, array $data): ?TransactionJournal
    {
        $submission = [
            'transactions' => [
                $data,
            ],
        ];
        /** @var TransactionJournalFactory $factory */
        $factory = app(TransactionJournalFactory::class);
        $factory->setUser($transactionGroup->user);
        try {
            $collection = $factory->create($submission);
        } catch (FireflyException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new FireflyException(sprintf('Could not create new transaction journal: %s', $e->getMessage()), 0, $e);
        }
        $collection->each(
            function (TransactionJournal $journal) use ($transactionGroup) {
                $transactionGroup->transactionJournals()->save($journal);
            }
        );
        if (0 === $collection->count()) {
            return null;
        }

        return $collection->first();
    }

}
