document.addEventListener('DOMContentLoaded', function() {
	var trafficSourceInput = document.getElementById('trafficSource');

	// Function to check the input against the allowed pattern
	function isValidInput(input) {
		var pattern = /^[a-zA-Z0-9 _.-]*$/;
		return pattern.test(input);
	}

	// Event listener for typing in the input
	trafficSourceInput.addEventListener('keypress', function(e) {
		var char = String.fromCharCode(e.which);
		if (!isValidInput(char)) {
			e.preventDefault();
		}
	});

	// Event listener for pasting into the input
	trafficSourceInput.addEventListener('paste', function(e) {
		var pasteData = e.clipboardData.getData('text');
		if (!isValidInput(pasteData)) {
			e.preventDefault();
		}
	});
});

jQuery(document).ready(function($) {
	$('#requestPayoutBtn').click(function() {
		document.querySelector('.loader').style.display = 'inline-block';
		var sbmtButton = document.querySelector('#requestPayoutBtn');
		sbmtButton.disabled = true;
		var data = {
			'action': 'request_payout',
			'nonce': affiliate_data.payoutRequestNonce,
			'user_id': affiliate_data.currentAffUserId
		};

		$.post(affiliate_data.ajax_url, data, function(response) {
			if (response.success) {
				alert('Payout request submitted.');
				$('#payoutRequestForm').html('<p>Your payout request has been submitted and is being reviewed.</p>');
			} else {
				alert('There was an error processing your request.');
			}
			sbmtButton.disabled = false;
			document.querySelector('.loader').style.display = 'none';
		});
	});

	$('#savePaypalEmail').click(function() {
		var paypalEmail = $('#paypalEmail').val();

		// Simple email validation
		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailPattern.test(paypalEmail)) {
			alert('Please enter a valid PayPal email.');
			return;
		}

		// Prepare data for AJAX call
		var data = {
			'action': 'save_paypal_email',
			'nonce': affiliate_data.savePaypalEmailNonce,
			'paypal_email': paypalEmail,
			'user_id': affiliate_data.currentAffUserId
		};

		// AJAX call to save the PayPal email
		$.post(affiliate_data.ajax_url, data, function(response) {
			if (response.success) {
				alert('PayPal email saved successfully.');
			} else {
				alert('There was an error saving your PayPal email.');
			}
		});
	});

	$('#cancelPayoutBtn').click(function() {
		document.querySelector('.loader').style.display = 'inline-block';
		var sbmtButton = document.querySelector('#cancelPayoutBtn');
		sbmtButton.disabled = true;
		var payoutId = $(this).data('payout-id');
		var data = {
			'action': 'cancel_payout_request',
			'nonce': affiliate_data.payoutCancelNonce,
			'payout_id': payoutId
		};

		$.post(affiliate_data.ajax_url, data, function(response) {
			if (response.success) {
				alert('Payout request canceled.');
				$('#payoutRequestForm').html('<button id="requestPayoutBtn">Request Payout</button>');
			} else {
				alert('There was an error processing your request.');
			}
			sbmtButton.disabled = false;
			document.querySelector('.loader').style.display = 'none';
		});
	});
});
