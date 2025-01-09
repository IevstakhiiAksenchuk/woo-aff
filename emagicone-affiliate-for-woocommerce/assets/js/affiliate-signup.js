jQuery(document).ready(function ($) {
  $("#emagicone_password").on("keyup", function () {
    var password = $(this).val();
    var strengthBar = document.getElementById("password_strength");
    var submitButton = document.getElementById("form_submit_button");

    if (password.length === 0) {
      strengthBar.innerHTML = "";
      strengthBar.className = ""; // Reset classes
      submitButton.disabled = true;
      submitButton.title = "Password is not strong enough";
      return;
    }

    var result = zxcvbn(password);
    var score = result.score;

    strengthBar.className = ""; // Reset classes
    console.log(score);
    switch (score) {
      case 0:
      case 1:
      case 2:
        strengthBar.innerHTML = ["Very Weak", "Weak", "Weak", "Medium"][score];
        strengthBar.classList.add(
          [
            "password-strength-very-weak",
            "password-strength-weak",
            "password-strength-weak",
            "password-strength-moderate",
          ][score]
        );
        submitButton.disabled = true;
        submitButton.title = "Password is not strong enough";
        break;
      case 3:
        strengthBar.innerHTML = "Medium";
        strengthBar.classList.add("password-strength-moderate");
        submitButton.disabled = false;
        submitButton.title = "";
        break;
      case 4:
        strengthBar.innerHTML = "Strong";
        strengthBar.classList.add("password-strength-strong");
        submitButton.disabled = false;
        submitButton.title = "";
        break;
    }
  });
});

//Recaptcha Code
document.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("affiliate_signup_form");

  // Define default values
  var recaptchaSiteKey = "";
  var useRecaptcha = "";

  // Check if the emagiconeAffiliate object exists before accessing properties
  if (typeof emagiconeAffiliate !== "undefined") {
    recaptchaSiteKey = emagiconeAffiliate.recaptchaSiteKey;
    useRecaptcha = emagiconeAffiliate.useRecaptcha;
  }

  if (form && useRecaptcha === "1" && recaptchaSiteKey) {
    loadRecaptcha(recaptchaSiteKey, form);
  }
});

function loadRecaptcha(recaptchaSiteKey, form) {
  grecaptcha.ready(function () {
    form.onsubmit = function (event) {
      event.preventDefault(); // Prevent the default form submission
      var password = document.getElementById("emagicone_password").value;
      if (
        password.length < 8 ||
        !password.match(/[a-zA-Z]/) ||
        !password.match(/\d/) ||
        !password.match(/[^a-zA-Z\d]/)
      ) {
        alert(
          "Your password is not strong enough. Please use a stronger password."
        );
        return;
      }
      grecaptcha
        .execute(recaptchaSiteKey, { action: "submit" })
        .then(function (token) {
          var hiddenInput = document.createElement("input");
          hiddenInput.setAttribute("type", "hidden");
          hiddenInput.setAttribute("name", "g-recaptcha-response");
          hiddenInput.setAttribute("value", token);
          form.appendChild(hiddenInput);
          form.submit(); // Submit the form
        });
    };
  });
}
