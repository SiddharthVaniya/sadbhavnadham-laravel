@extends('layouts.donate')

@section('title', 'Donate with Danamojo | ' . $branding['name'])

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="mb-3">Donate from Outside India</h3>
                        <div class="p-3 border rounded">
                            <!‐‐BEGIN danamojo code‐‐>

                                <script src="https://danamojo.org/dm/js/widget.js" type="text/javascript"></script>
                                <script>
                                    setTimeout(function() {
                                        if (document.getElementById("ngoContentContainer").innerHTML.length < 40) {
                                            document.getElementById("ngoContentContainer").innerHTML =
                                                "<center> <p style='color:#a94442;'>we are sorry that our systems are down. we will be up shortly. apologies for the inconvenience.</p></center>";
                                        }
                                    }, 20000);

                                    (function () {
                                        var notified = null;
                                        function notifyIfNeeded() {
                                            try {
                                                var params = new URLSearchParams(window.location.search);
                                                var id = params.get('donationInfoId');
                                                if (!id || id === notified) {
                                                    return;
                                                }
                                                notified = id;
                                                var key = 'danamojo-notify:' + id;
                                                try {
                                                    if (sessionStorage.getItem(key)) {
                                                        return;
                                                    }
                                                    sessionStorage.setItem(key, '1');
                                                } catch (e) {}
                                                var body = {
                                                    donationInfoId: parseInt(id, 10),
                                                    landing_url: window.location.href
                                                };
                                                ['dmStatus', 'dmTotalAmount', 'sid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id', 'aid'].forEach(function (name) {
                                                    var value = params.get(name);
                                                    if (value) {
                                                        body[name] = value;
                                                    }
                                                });
                                                if (document.referrer) {
                                                    body.referrer = document.referrer;
                                                }
                                                fetch('/api/donate/danamojo/notify', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                                    body: JSON.stringify(body),
                                                    keepalive: true
                                                }).catch(function () {});
                                            } catch (e) {}
                                        }
                                        notifyIfNeeded();
                                        setInterval(notifyIfNeeded, 2000);
                                    })();
                                </script>
                                <div id="dmScriptContainer" style="display:none;"><a href="#">Donate Now</a></div>
                                <div id="ngoContentContainer" iNGOId="1362" oDisplay="product" oDisplayTab="once,monthly"
                                    oQRCode="YES">
                                    <center><img alt="please wait..."
                                            src="https://danamojo.org/dm/css/images/loading.gif" /></center>
                                </div>

                                <!‐‐END danamojo code‐‐>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
