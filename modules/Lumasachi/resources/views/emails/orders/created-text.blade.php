New Order Created: #{{ $order->id }}

A new order has been created.
Order ID: {{ $order->id }}

View Order: {{ route('orders.show', $order) }}

Thank you for your business!
