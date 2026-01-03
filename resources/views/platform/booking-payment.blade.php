@if($payment)
<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5 class="text-black mb-3">💳 Платеж</h5>
    
    <div class="row">
        <div class="col-md-6">
            <p><strong>Сумма:</strong> ${{ number_format($payment->amount, 2) }}</p>
            <p><strong>Статус:</strong> 
                <span class="badge bg-{{ $payment->status->color() }}">{{ $payment->status->label() }}</span>
            </p>
            <p><strong>Дата:</strong> {{ $payment->created_at->format('d.m.Y H:i') }}</p>
            @if($payment->verified_at)
                <p><strong>Проверен:</strong> {{ $payment->verified_at->format('d.m.Y H:i') }}</p>
            @endif
        </div>
        <div class="col-md-6">
            @if($payment->has_receipt)
                <p><strong>Чек:</strong></p>
                @if($payment->is_image_receipt)
                    <a href="{{ $payment->receipt_url }}" target="_blank">
                        <img src="{{ $payment->receipt_url }}" 
                             alt="Чек" 
                             class="img-fluid rounded" 
                             style="max-height: 200px;">
                    </a>
                @else
                    <a href="{{ $payment->receipt_url }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        📄 {{ $payment->receipt_file_name }}
                    </a>
                @endif
            @else
                <p class="text-muted">Чек не загружен</p>
            @endif
        </div>
    </div>
</div>
@endif
