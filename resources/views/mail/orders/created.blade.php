<x-mail::message>
    # {{ __('mail.order_created.heading') }}

<x-mail::subcopy>
    {{ __('mail.order_created.subcopy') }}
</x-mail::subcopy>

    {{ __('mail.order_created.details') }}
<x-mail::panel>
    - {{ __('mail.order_created.order_id', ['uuid' => $order->uuid]) }}
    - {{ __('mail.order_created.title', ['title' => $order->title]) }}
    - {{ __('mail.order_created.customer', ['name' => $order->customer->full_name]) }}
    - {{ __('mail.order_created.assigned_to', ['name' => $order->assignedTo?->full_name ?? __('mail.order_created.unassigned')]) }}
    - {{ __('mail.order_created.status', ['status' => $order->status->getLabel()]) }}
    - {{ __('mail.order_created.priority', ['priority' => $order->priority->getLabel()]) }}
</x-mail::panel>

    <x-mail::button url="{{ route('web.orders.show', [$order->uuid]) }}"
                    color="red">{{ __('mail.order_created.action') }}</x-mail::button>

    {{ __('mail.order_created.thanks') }}
    {{ config('app.name') }}
</x-mail::message>
