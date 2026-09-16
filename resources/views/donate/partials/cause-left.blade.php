<div class="cause-panel">

    {{-- Cause Image --}}
    @if(!empty($image))
        <img src="{{ $image }}" alt="{{ $title ?? 'Donation Cause' }}">
    @endif

    <div class="cause-panel-content">

        {{-- Cause Title --}}
        @if(!empty($title))
            <h1>{{ $title }}</h1>
        @endif

        {{-- Cause Description --}}
        @if(!empty($description))
            <p>{{ $description }}</p>
        @endif

        {{-- Donation Options (Optional) --}}
        @if(!empty($options) && is_array($options))
            <div class="cause-options">
                @foreach($options as $option)
                    <div class="option-card" data-amount="{{ $option['amount'] ?? '' }}" data-meta='@json($option['meta'] ?? [])'>

                        <div class="title">
                            {{ $option['label'] }}
                        </div>

                        @if(!empty($option['amount']))
                            <div class="amount">
                                ₹{{ number_format($option['amount']) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Info / Trust Note --}}
        @if(!empty($note))
            <div class="trust-note">
                {{ $note }}
            </div>
        @endif

    </div>
</div>
