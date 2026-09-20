<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Utilities;

use App\Actions\Utilities\PayBill;
use App\Actions\Utilities\SaveBillLines;
use App\Forms\Definitions\UtilityBillForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\UtilityBillRequest;
use App\Models\UtilityBill;
use App\Tables\Definitions\UtilityBillTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UtilityBillController extends AdminResourceController
{
    protected function table(): UtilityBillTable
    {
        return new UtilityBillTable;
    }

    protected function form(): UtilityBillForm
    {
        return new UtilityBillForm;
    }

    protected function modelClass(): string
    {
        return UtilityBill::class;
    }

    protected function resourceName(): string
    {
        return 'utility-bills';
    }

    protected function pagePath(): string
    {
        return 'Utilities/Bills';
    }

    protected function requestClass(): string
    {
        return UtilityBillRequest::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['document_path' => 'utility-bills'];
    }

    /**
     * Overridden because the breakdown is neither a column nor a relation sync,
     * so it has to leave the data before partition() mass assigns it.
     *
     * SaveBillLines is resolved inside rather than injected: adding a required
     * parameter to an overridden method is a signature violation in PHP.
     */
    public function store(): RedirectResponse
    {
        $form = $this->form();
        $data = $this->validated();
        $lines = $form->pullLines($data);

        $bill = $this->storeRecord->handle(UtilityBill::class, $form->partition($data));

        // StoreRecord is declared to return Model; narrowing here is what lets
        // SaveBillLines take a UtilityBill without a cast.
        abort_unless($bill instanceof UtilityBill, 500);

        resolve(SaveBillLines::class)->handle($bill, $lines);

        $this->notifier->success('Bill created');

        return to_route($this->indexRoute());
    }

    public function update(Request $request): RedirectResponse
    {
        $form = $this->form();
        $data = $this->validated();
        $lines = $form->pullLines($data);

        $record = $this->resolveRecord($request);

        abort_unless($record instanceof UtilityBill, 404);

        $bill = $this->updateRecord->handle($record, $form->partition($data));

        resolve(SaveBillLines::class)->handle($bill, $lines);

        $this->notifier->success('Bill updated');

        return to_route($this->indexRoute());
    }

    public function pay(UtilityBill $utilityBill, PayBill $payBill): RedirectResponse
    {
        try {
            $payBill->handle($utilityBill);
            $this->notifier->success('Bill paid', 'Recorded as an expense.');
        } catch (RuntimeException $runtimeException) {
            $this->notifier->danger('Could not pay', $runtimeException->getMessage());
        }

        return to_route($this->indexRoute());
    }
}
