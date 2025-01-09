document.addEventListener("DOMContentLoaded", function (event) {
    var modal = document.getElementById("affiliateLinkModal");
    var span = document.getElementsByClassName("close")[0];
    
    if (span) {
        span.onclick = function () {
            modal.style.display = "none";
        };
    }
    if (modal) {
        window.onclick = function (event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        };
    }
});
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    if (document.querySelector(".tablinks")) {
        // Open the first tab by default
        document.addEventListener(
                "DOMContentLoaded",
                function () {
                    document.querySelector(".tablinks").click();
                }
        );
    }

    function openModal(productId, btnElement) {
        var modal = document.getElementById("affiliateLinkModal");
        
        // Clear previous values
        document.getElementById("productId").value = '';
        document.getElementById("productUrl").value = ''; // Clear the product URL
        document.getElementById("trafficSource").value = ''; // Clear the trafficSource
        document.getElementById("generatedLink").innerHTML = ''; // Clear any previously generated link

        var productUrl = btnElement.getAttribute('data-producturl');
        document.getElementById("productId").value = productId;
        document.getElementById("productUrl").value = productUrl; // Store the product URL in a hidden input
        modal.style.display = "block";
    }

    
    function generateAffiliateLink() {
        document.querySelector('.loader').style.display = 'inline-block';
        var sbmtButton = document.querySelector('#affiliateLinkForm button');
        sbmtButton.disabled = true;
        var productId = document.getElementById("productId").value;
        var trafficSource = document.getElementById("trafficSource").value;
        var productUrl = document.getElementById("productUrl").value;

        // Check if trafficSource is empty
        if (!trafficSource.trim()) {
            alert("Please enter a traffic source.");
            document.querySelector('.loader').style.display = 'none';
            sbmtButton.disabled = false;
            return; // Exit the function if trafficSource is empty
        }

        var utmContentObj = {
            "campaign_id": affiliate_data.currentUserCampaignId,
            "traffic_source": trafficSource,
            "account_id": parseInt(affiliate_data.currentAffUserId)
        };
        var utmContentBase64 = btoa(JSON.stringify(utmContentObj));

        utmContentBase64 = utmContentBase64.replace(/=+$/, '');

        var affiliateLink = productUrl
                + "?utm_source=" + encodeURIComponent(trafficSource)
                + "&utm_medium=affiliate"
                + "&utm_id=" + encodeURIComponent(affiliate_data.currentUserCampaignId)
                + "&aw_affiliate=" + utmContentBase64;

        // AJAX call to server to save or get the link
        var data = {
            'action': 'save_or_get_affiliate_link',
            'security': affiliate_data.nonce, // Nonce added here
            'affiliate_id': affiliate_data.currentAffUserId,
            'product_id': productId,
            'traffic_source': trafficSource,
            'campaign_id': affiliate_data.currentUserCampaignId,
            'affiliate_link': affiliateLink,
            'aw_affiliate': utmContentBase64
        };

        jQuery.post(
                affiliate_data.ajax_url,
                data,
                function (response) {
                    if (response.success) {
                        document.getElementById("generatedLink").innerHTML = "Your affiliate link: <a href='" + response.data + "' target='_blank'>" + response.data + "</a>";
                    } else {
                        document.getElementById("generatedLink").innerHTML = "<p class='text-danger error-wrapper'>" + response.data + "</p>";
                    }

                    document.querySelector('.loader').style.display = 'none';
                    sbmtButton.disabled = false;
                }
        );
    }

