<?php
include('partials-front/menu.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
require 'vendor/autoload.php';

if (!isset($_SESSION['userLoggedIn'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['client_id'];
$totalPrice = 0;
$deliveryOption = '';

?>
<br><br><br><br><br><br><br><br>
<body style="text-align: center"; >

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/AlertifyJS/1.13.1/css/alertify.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/AlertifyJS/1.13.1/css/themes/default.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/AlertifyJS/1.13.1/alertify.min.js"></script>

<h1>Checkout</h1>
<br>
<form method="post">
<div class="delivery-address">
    <h2>Delivery Address</h2>
    <?php
    // Fetch the delivery address for the logged-in user
    $sql = "SELECT * FROM client_list WHERE id = $userId";
    $res = mysqli_query($conn, $sql);

    if ($res == TRUE) {
        $count = mysqli_num_rows($res);

        if ($count > 0) {
            $row = mysqli_fetch_assoc($res);
            $fullname = $row['fullname'];
            $contact = $row['contact'];
            $address = $row['address'];

            // Pass the user's delivery address to JavaScript
            echo "<script>";
            echo "var deliveryAddress = {";
            echo "    fullname: '" . $fullname . "',";
            echo "    contact: '" . $contact . "',";
            echo "    address: '" . $address . "'";
            echo "};";
            echo "</script>";
        }
    }
    ?>
    <p>Name: <?php echo $fullname; ?></p>
    <p>Contact Number: <?php echo $contact; ?></p>
    <p>Address: <?php echo $address; ?></p>
</div>

<table class="purchase-order">

    <tr>
        <th>Product Order</th>
        <th>Image</th>
        <th>Color and Size</th>
        <th>Unit Price</th>
        <th>Quantity</th>
        <th>Price</th>
    </tr>

    <tr>
        <?php
       $sql = "SELECT c.id, c.product_id, c.quantity, c.color, c.size, p.name, p.price, p.image_path, d.discount_percentage
       FROM cart_list c
       INNER JOIN product_list p ON c.product_id = p.id
       LEFT JOIN discounts d ON c.product_id = d.product_id AND NOW() BETWEEN d.start_time AND d.end_time
       WHERE c.client_id = $userId";
        $res = mysqli_query($conn, $sql);
        $count = mysqli_num_rows($res);
        $sn = 1;

        if ($count > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $cart_id = $row['id'];
                $product_id = $row['product_id'];
                $product_name = $row['name'];
                $product_price = $row['price'];
                $product_quantity = $row['quantity'];
                $product_total = $product_price * $product_quantity;
                $product_image = $row['image_path'];
                $product_color = $row['color'];
                $product_size = $row['size'];


                $discount_percentage = $row['discount_percentage'];

                $discounted_price = $product_price - ($product_price * ($discount_percentage / 100));
                $product_total = $discounted_price * $product_quantity; // Updated calculation with discount
                $product_price_display = $discounted_price;

                ?>
                 <tr>
                    <td><?php echo $product_name; ?></td>
                    <td><?php
                        if ($product_image == "") {
                            echo "<div class='error'>Image not Added.</div>";
                        } else {
                            ?>
                            <img src="<?php echo SITEURL; ?>images/bike/<?php echo $product_image; ?>"
                                width="50px">
                            <?php
                        }
                        ?></td>
                    <td><?php echo "Color: " . $product_color . "<br>Size: " . $product_size; ?></td>
                    <td>₱<?php echo number_format($product_price_display); ?></td>
                    <td><?php echo $product_quantity; ?></td>
                    <td>₱<?php echo number_format($product_total); ?></td>
                </tr>

                <?php
                $totalPrice += $product_total;
            }
        }

        ?>
    </tr>
</table>


<div class="order-message">
    <label for="order_message">Message:</label>
    <textarea name="order_message" id="order_message" rows="4" cols="50"></textarea>
</div>

<div class="payment-method">
    <h2>Payment Method</h2>
    <button class="select-payment-button" type="button" onclick="showPaymentButtons()">Select Payment Method</button>
    <div class="payment-buttons">
        <button class="payment-button" type="button" onclick="showPaymentDetails('Pickup')">Pick Up</button>
        <button class="payment-button" type="button" onclick="showPaymentDetails('Cash on Delivery')">Cash on Delivery</button>
        <button class="payment-button" type="button" onclick="showPaymentDetails('Gcash')">Gcash</button>
    </div>
    <div class="payment-details">
        <!-- Remove the duplicate input element -->
    </div>
</div>




<!-- Keep this hidden input field for payment_method -->
<input type="hidden" name="payment_method" value="">

<!-- Disable the submit button by default -->
<input type="submit" name="checkout" value="Checkout" class="btn-secondary" disabled>
<input type="hidden" name="delivery_address" id="delivery_address">
</form>
</body>

<script>
    // Create a JavaScript object to store the delivery address
    var deliveryAddress = {
        fullname: "<?php echo $fullname; ?>",
        contact: "<?php echo $contact; ?>",
        address: "<?php echo $address; ?>"
    };

    function showPaymentButtons() {
        
        const paymentButtons = document.querySelector('.payment-buttons');
        paymentButtons.style.display = 'flex';
    }

    function hidePaymentButtons() {
        const paymentButtons = document.querySelector('.payment-buttons');
        paymentButtons.style.display = 'none';
    }

    function showPaymentDetails(paymentMethod) {
    const paymentDetails = document.querySelector('.payment-details');
    const paymentMethodInput = document.querySelector('input[name="payment_method"]');
    const deliveryAddressInput = document.querySelector('input[name="delivery_address"]');
    const orderMessageTextarea = document.querySelector('textarea[name="order_message"]');
    const additionalFee = 300; // Additional fee for Cash on Delivery

    if (paymentMethodInput) {
        let totalWithFee = <?php echo $totalPrice; ?>; // Initial total without fee

        if (paymentMethod === 'Cash on Delivery') {
            const maxQuantityForCOD = 5;
            let totalQuantity = 0;

            // Calculate the total quantity in the cart
            <?php
            $cartItemsSql = "SELECT SUM(quantity) AS total_quantity FROM cart_list WHERE client_id = $userId";
            $cartItemsResult = mysqli_query($conn, $cartItemsSql);
            if ($cartItemsResult) {
                $totalQuantity = mysqli_fetch_assoc($cartItemsResult)['total_quantity'];
            }
            ?>
            totalQuantity = <?php echo $totalQuantity; ?>;

            if (totalQuantity > maxQuantityForCOD) {
                alertify.alert('Exceeded Cash on Delivery Quantity Limit', 'Cash on Delivery quantity is limited to 5 items. Please reduce the quantity or choose another payment method.');
                return;
            }

            totalWithFee += additionalFee; // Add the additional fee for COD
        }

        const formattedTotalWithFee = new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(totalWithFee);

        orderMessageTextarea.value = orderMessageTextarea.value;

        // Set the delivery_address input value
        deliveryAddressInput.value = JSON.stringify(deliveryAddress.address);

        paymentDetails.innerHTML = `
            Selected Payment Method: ${paymentMethod}<br>
            Name: ${deliveryAddress.fullname}<br>
            Contact Number: ${deliveryAddress.contact}<br>
            Address: ${deliveryAddress.address}<br>
            Additional Fee: ₱${additionalFee}<br>
            Total Price: ${formattedTotalWithFee}
        `;

        hidePaymentButtons();

        paymentMethodInput.value = paymentMethod;

        document.querySelector('input[name="checkout"]').disabled = false;
    }
}



</script>
</html>

<?php

if (isset($_POST['checkout'])) {
  
    // Step 1: Insert a new order record into order_list
    $refCode = "ORD_" . date("YmdHis") . "_" . $userId;
    $paymentMethod = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : '';
    $deliveryAddress = isset($_POST['delivery_address']) ? mysqli_real_escape_string($conn, $_POST['delivery_address']) : '';
    $orderMessage = isset($_POST['order_message']) ? mysqli_real_escape_string($conn, $_POST['order_message']) : '';

    $additionalFee = 0;

    if ($paymentMethod === 'Cash on Delivery') {
        $additionalFee = 300;
    }

    $totalPrice += $additionalFee;
    

    $insertOrderSql = "INSERT INTO order_list (ref_code, client_id, total_amount, delivery_address, payment_method, message, status, order_receive)
    VALUES ('$refCode', $userId, $totalPrice, '$deliveryAddress', '$paymentMethod', '$orderMessage', 0, 0)";



    if (mysqli_query($conn, $insertOrderSql)) {
        // Step 2: Retrieve the order_id of the newly inserted order
        $orderId = mysqli_insert_id($conn);

        // Step 3: Retrieve the cart items
        $cartItemsSql = "SELECT * FROM cart_list WHERE client_id = $userId";
        $cartItemsResult = mysqli_query($conn, $cartItemsSql);

        $notificationMessage = "Your order has been placed successfully. Your order reference code is: $refCode";
        $notificationType = "Order";
        $timestamp = date('Y-m-d H:i:s');
        $status = "new";
        $isRead = 0;
        $promoId = 0; // Replace this with the actual promo ID if applicable

        $insertNotificationSql = "INSERT INTO notifications (user_id, message, notification_type, timestamp, status, is_read, promo_id) 
                                  VALUES ($userId, '$notificationMessage', '$notificationType', '$timestamp', '$status', $isRead, $promoId)";

        if (!mysqli_query($conn, $insertNotificationSql)) {
            // Handle the insert notification query error
            mysqli_rollback($conn);
            echo "Error inserting notification record: " . mysqli_error($conn);
            exit;
        }

        $insertAdminNotificationSql = "INSERT INTO admin_notifications (order_id, order_products_id, notification_type, is_approved, is_read, timestamp) 
        VALUES ($orderId, $product_id, 'order', 1, 0, current_timestamp())";

            if (!mysqli_query($conn, $insertAdminNotificationSql)) {
            // Handle the insert admin notification query error
            mysqli_rollback($conn);
            echo "Error inserting admin notification record: " . mysqli_error($conn);
            exit;
            }


        while ($cartItem = mysqli_fetch_assoc($cartItemsResult)) {
            $productId = $cartItem['product_id'];
            $quantity = $cartItem['quantity'];
            $color = $cartItem['color'];
            $size = $cartItem['size'];

            // Retrieve product price from product_list based on product_id
            $productPriceSql = "SELECT price FROM product_list WHERE id = $productId";
            $productPriceResult = mysqli_query($conn, $productPriceSql);
            $productPrice = mysqli_fetch_assoc($productPriceResult)['price'];

            // Insert the cart item into order_products
            $insertOrderProductSql = "INSERT INTO order_products (order_id, product_id, quantity, price_per_unit, color, size)
                                      VALUES ($orderId, $productId, $quantity, $productPrice, '$color', '$size')";

            if (!mysqli_query($conn, $insertOrderProductSql)) {
                // Handle the insert order product query error
                mysqli_rollback($conn);
                echo "Error inserting order product record: " . mysqli_error($conn);
                exit;
            }

            // Update the stock_list table to deduct the quantity
            $updateStockSql = "UPDATE stock_list AS sl
                        INNER JOIN product_colors_sizes AS pcs ON sl.product_colors_sizes_id = pcs.id
                        SET sl.quantity = sl.quantity - $quantity 
                        WHERE pcs.product_id = $productId AND pcs.color = '$color' AND pcs.size = '$size'";
            if (!mysqli_query($conn, $updateStockSql)) {
                // Handle the update stock query error
                mysqli_rollback($conn);
                echo "Error updating stock records: " . mysqli_error($conn);
                exit;
            }
        }

        // Step 5: Clear the user's cart
        $clearCartSql = "DELETE FROM cart_list WHERE client_id = $userId";
        mysqli_query($conn, $clearCartSql);

        // Step 6: Commit the changes
        mysqli_commit($conn);

        // Step 4: Commit the changes
        mysqli_commit($conn);
        $id = $orderId;

        $sql1 = "SELECT * FROM client_list WHERE id = $userId";
        $result = mysqli_query($conn, $sql1);
        if ($result && mysqli_num_rows($result) > 0) {
            $clientData = mysqli_fetch_assoc($result);
            $clientEmail = $clientData['email'];
    
            $to = $clientEmail; // Client's email address
            $subject = "Order Receipt";
    
            // Modify the HTML content to include an image and enhanced design
            $mailContent = '<!DOCTYPE html>
            <html>
            <head>
                <title>Order Receipt</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        padding: 20px;
                    }
                    .container {
                        border: 1px solid #ccc;
                        border-radius: 5px;
                        padding: 20px;
                        margin-bottom: 20px;
                    }
                    .product-img {
                        max-width: 100px;
                        max-height: 100px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Hello, ' . $fullname . '!</h2>
                    <p>Thank you for your order. Your order reference code is: <strong>' . $refCode . '</strong></p>
                    <p>Total amount: ₱' . $totalPrice . '</p>
                </div>';
    
            // Add the product details to the email content
            $mailContent .= '<div class="container">';
            $mailContent .= '<h3>Products Ordered:</h3>';

            // Retrieve the cart items with product details
            $cartItemsSql = "SELECT c.product_id, c.quantity, c.color, c.size, p.name, p.price, p.image_path 
                            FROM cart_list c 
                            INNER JOIN product_list p 
                            ON c.product_id = p.id 
                            WHERE c.client_id = $userId";
            $cartItemsResult = mysqli_query($conn, $cartItemsSql);
    
            // Loop through each product to include its details
            $cartItemsResult = mysqli_query($conn, $cartItemsSql);
            while ($cartItem = mysqli_fetch_assoc($cartItemsResult)) {
                // Get the product details
                $productName = $cartItem['name'];
                $productImage = SITEURL . 'images/bike/' . $cartItem['image_path'];
                $productPrice = $cartItem['price'];
                $productQuantity = $cartItem['quantity'];
                $productTotal = $productPrice * $productQuantity;
                $productColor = $cartItem['color'];
                $productSize = $cartItem['size'];
    
                // Add product details to the email content
                $mailContent .= '<div>';
                $mailContent .= '<img src="' . $productImage . '" alt="' . $productName . '" class="product-img">';
                $mailContent .= '<p>Name: ' . $productName . '</p>';
                $mailContent .= '<p>Color: ' . $productColor . '</p>';
                $mailContent .= '<p>Size: ' . $productSize . '</p>';
                $mailContent .= '<p>Price: ₱' . $productPrice . '</p>';
                $mailContent .= '<p>Quantity: ' . $productQuantity . '</p>';
                $mailContent .= '<p>Total: ₱' . $productTotal . '</p>';
                $mailContent .= '</div>';
            }
            $mailContent .= '</div>';
    
            // Add the closing tags and signature
            $mailContent .= '
                <p>Thank you for shopping with us!</p>
                <p>Regards,</p>
                <p>Skid Alley</p>
            </body>
            </html>';
    
           
    
            
    
            $mail = new PHPMailer(true);
            try 
            {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Set your SMTP host
                $mail->SMTPAuth = true;
                $mail->Username = 'iansalgado567@gmail.com'; // Set your SMTP username
                $mail->Password = 'obid wjcj ztjv nhbm';  // SMTP password
                $mail->SMTPSecure = 'tls'; // Enable TLS encryption, `ssl` also accepted
                $mail->Port = 587; // TCP port to connect to
    
                $mail->setFrom('your_email@example.com', 'Skid Alley');
                $mail->addAddress($to); // Add a recipient
    
                $mail->isHTML(true); // Set email format to HTML
                $mail->Subject = $subject;
                $mail->Body = $mailContent;
    
                $mail->send();
                echo 'Message has been sent';
            } 

            catch (Exception $e) 
            {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } 
        else {
            echo 'Client data not found.';
        }

        // Redirect to the order status page
        header("Location: " . SITEURL . "orderstatus.php?id=$id");
        exit();

    } else {
        // Handle the insert order query error
        mysqli_rollback($conn);
        echo "Error inserting order record: " . mysqli_error($conn);
    }

    //Step 5: Clear the user's cart

    $clearCartSql = "DELETE FROM cart_list WHERE client_id = $userId";
    mysqli_query($conn, $clearCartSql);
}

?>

<br><br><br>
<?php include('partials-front/footer.php'); ?>