<?php

/*
 * ChangedPiggyBankAmount.php
 * Copyright (c) 2023 james@firefly-iii.org
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

namespace FireflyIII\Events;

use FireflyIII\Models\PiggyBank;
use FireflyIII\Models\TransactionGroup;
use FireflyIII\Models\TransactionJournal;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Class ChangedPiggyBankAmount
 */
class ChangedPiggyBankAmount extends Event
{
    use SerializesModels;

    public PiggyBank           $piggyBank;
    public ?TransactionJournal $transactionJournal;
    public ?TransactionGroup   $transactionGroup;
    public string              $amount;

    /**
     * Create a new event instance.
     *
     * @param  PiggyBank  $piggyBank
     * @param  string  $amount
     * @param  TransactionJournal|null  $transactionJournal
     * @param  TransactionGroup|null  $transactionGroup
     */
    public function __construct(PiggyBank $piggyBank, string $amount, ?TransactionJournal $transactionJournal, ?TransactionGroup $transactionGroup)
    {
        Log::debug(sprintf('Created piggy bank event for piggy bank #%d with amount %s', $piggyBank->id, $amount));
        $this->piggyBank          = $piggyBank;
        $this->transactionJournal = $transactionJournal;
        $this->transactionGroup   = $transactionGroup;
        $this->amount             = $amount;
    }
}
