
// hide alerts when there were some...
$( function() {
    $('.alert').delay(3000).fadeOut();
});


function setup2fa() {
    if (!$('#username').val()) {
	alert('Please enter a username first');
	return;
    }
    // get a random 2fa string.
    $.post("ajax.php",{
	"action":"2fa",
	"username":$('#username').val()
    },function (otp) {
	document.cookie = "OTP="+escape(otp);
	$('#totp').val(otp);
	$('#div2fa').html('<img src="otp-qrcode.php" />');
	$('#totpcheckdiv').show();
    });
    
    return true;
}
