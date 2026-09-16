<h2>Oops! Your donation could not be processed.</h2>
<p>Something went wrong while processing your payment.</p>
<p><strong>Reason:</strong> {{ $data['payload']['payment']['entity']['error_reason'] ?? 'Unknown' }}</p>
<p><strong>Payment ID:</strong> {{ $data['payload']['payment']['entity']['id'] }}</p>
