<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Ticket de compra</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .ticket {
            max-width: 450px;
            margin: 20px auto;
            padding: 20px;
        }

        h1, h2, h3, h4 {
            text-align: center;
            margin-bottom: 10px;
        }

        .info {
            margin-bottom: 20px;
        }

        .info div {
            margin-bottom: 5px;
        }

        .footer {
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
    </style>


</head>
<body>
    <div class="ticket">
        <h4>Número de orden: {{ $order->id }}</h4>
        <div class="info">
            <h3>Información de la compañia</h3>
            <div>Nombre: Eccomerce S.A.C</div>
            <div>RUC: 009090909001</div>
            <div>Telefono: 908765890</div>
            <div>Correo: eccomerce@gmail.com</div>
        </div>

        <div class="info">
            <h3>Datos del cliente</h3>
            <div>Nombre: {{ $order->address['receiver_info']['name'] }}</div>
            <div>Documento: {{ $order->address['receiver_info']['document_number'] }}</div>
            <div>Dirección: {{ $order->address['address_line_1'] }} - {{ $order->address['city'] }} </div>
            <div>Referencia: {{ $order->address['reference'] }} </div>
            <div>Teléfono: {{ $order->address['receiver_info']['phone'] }} </div>
        </div>

        <div class="footer">
            ¡Gracias por su compra!
        </div>
    </div>
</body>
</html>
