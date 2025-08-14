<x-mail::message>
# New Order Created: #{{ $order->id }}

A new order has been created.
Order ID: {{ $order->id }}

<x-mail::button :url="route('orders.show', $order)">
View Order
</x-mail::button>

Thank you for your order!
</x-mail::message>
