<x-mail::message>
    # New Order Created

<x-mail::subcopy>
    A new order has been created.
</x-mail::subcopy>

    Order Details:
<x-mail::panel>
- Order ID: __{{ $order->id }}__
- Title: __{{ $order->title }}__
- Customer: __{{ $order->customer->full_name }}__
- Assigned to: __{{ $order->assignedTo->full_name ?? 'Unassigned' }}__
- Status: __{{ $order->status->value }}__
- Priority: __{{ $order->priority->value }}__
</x-mail::panel>

<x-mail::button url="{{ route('orders.show', $order) }}" color="red">View Order</x-mail::button>

    Thank you,
    {{ config('app.name') }}
</x-mail::message>
