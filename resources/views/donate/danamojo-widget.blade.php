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
