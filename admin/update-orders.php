<?php include('partials/menu.php'); ?>

<div class="main-content">
    <div class="wrapper">
        <h1>Update Order</h1>
        <br/>

        <?php
        if (isset($_SESSION['add'])) {
            echo $_SESSION['add'];
            unset($_SESSION['add']);
        }

        $userId = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

        $id = $_GET['id'];
        $sql = "SELECT * FROM order_list WHERE id = $id";
        $res = mysqli_query($conn, $sql);

        if ($res == true) {
            $count = mysqli_num_rows($res);
            if ($count == 1) {
                $row = mysqli_fetch_assoc($res);
                $client_id = $row['client_id'];
                $total_amount = $row['total_amount'];
                $deliveryAddress = $row['delivery_address'];
                $paymentMethod = $row['payment_method'];
                $status = $row['status'];
                $order_receive = $row['order_receive'];
            } else {
                header('location:' . SITEURL . 'admin/orders.php');
            }
        }
        ?>

        <br/><br/><br/>

        <form action="" method="POST">
            <table class="tbl-30">
                <tr>
                    <td>Client Name: </td>
                    <td><?php echo $client_id; ?></td>
                </tr>

                <tr>
                    <td>Delivery Address: </td>
                    <td><?php echo $deliveryAddress; ?></td>
                </tr>

                <tr>
                    <td>Total Amount: </td>
                    <td>₱<?php echo number_format($total_amount); ?></td>
                </tr>

                <tr>
                    <td>Payment Method: </td>
                    <td><?php echo $paymentMethod; ?></td>
                </tr>

                <tr>
                    <td>Status: </td>
                    <td>
                        <select name="status" <?php echo ($order_receive == 1) ? 'disabled' : ''; ?>>
                            <option value="0" <?php if ($status == 0) echo 'selected'; ?>>Pending</option>
                            <option value="1" <?php if ($status == 1) echo 'selected'; ?>>Packed</option>
                            <option value="2" <?php if ($status == 2) echo 'selected'; ?>>For Delivery</option>
                            <option value="3" <?php if ($status == 3) echo 'selected'; ?>>On the Way</option>
                            <option value="4" <?php if ($status == 4) echo 'selected'; ?>>Delivered</option>
                            <option value="5" <?php if ($status == 5) echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </td>
                </tr>

            </table>

            <table class="tbl-30">
                <?php
                $sql2 = "SELECT op.order_id, 
                    p.name AS product_name, 
                    p.price AS product_price, 
                    p.image_path AS product_image,
                    op.color AS product_color,
                    op.size AS product_size,
                    op.quantity AS product_quantity
                    FROM order_products op
                    JOIN product_list p ON op.product_id = p.id
                    WHERE op.order_id = $id";
                $res2 = mysqli_query($conn, $sql2);
                while ($productRow = mysqli_fetch_assoc($res2)) {
                    $product_name = $productRow['product_name'];
                    $product_color = $productRow['product_color'];
                    $product_size = $productRow['product_size'];
                    $product_quantity = $productRow['product_quantity'];
                    $product_image = $productRow['product_image'];
                    ?>
                    <tr>
                        <td>Product : </td>
                        <td>
                            <img src="<?php echo SITEURL; ?>images/bike/<?php echo $product_image; ?>" width="100px"
                                 alt="<?php echo $product_name; ?>"><br>
                            <?php echo $product_name; ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Color and Size: </td>
                        <td><?php echo $product_color . ', ' . $product_size; ?></td>
                    </tr>

                    <tr>
                        <td>Quantity: </td>
                        <td><?php echo $product_quantity; ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>

            <input type="submit" name="submit" value="Update Status" class="btn-secondary">

        </form>

        <?php
        // Check if the form is submitted
        if (isset($_POST['submit'])) {
            $newStatus = $_POST['status'];

            // Update the status in the database
            $updateSql = "UPDATE order_list SET status = $newStatus WHERE id = $id";
            $updateRes = mysqli_query($conn, $updateSql);

            if ($updateRes) {
                $_SESSION['add'] = "Status updated successfully.";

                // Notification messages based on the new status
                $notificationData = [
                    1 => ["type" => "Packed", "message" => "Your Order Is Packed and ready for delivery."],
                    2 => ["type" => "For Delivery", "message" => "Your Order Is out for delivery."],
                    3 => ["type" => "On the Way", "message" => "Your Order Is on the way."],
                    4 => ["type" => "Delivered", "message" => "Your Order Has Been Delivered Successfully."]
                ];

                if (isset($notificationData[$newStatus])) {
                    $notificationType = $notificationData[$newStatus]["type"];
                    $notificationMessage = $notificationData[$newStatus]["message"];

                    // Insert the notification into the database
                    $userId = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
                    $timestamp = date('Y-m-d H:i:s');
                    $status = "new";
                    $isRead = 0;
                    $promoId = 0; // Replace this with the actual promo ID if applicable

                    $insertNotificationSql = "INSERT INTO notifications (user_id, message, notification_type, timestamp, status, is_read, promo_id) 
                                              VALUES ($userId, '$notificationMessage', '$notificationType', '$timestamp', '$status', $isRead, $promoId)";

                    mysqli_query($conn, $insertNotificationSql);
                }

                // If the new status is 'Cancelled' (status code 5)
                if ($newStatus == 5) {
                    // Get the products from the canceled order
                    $getProductsSql = "SELECT * FROM order_products WHERE order_id = $id";
                    $productsResult = mysqli_query($conn, $getProductsSql);

                    while ($product = mysqli_fetch_assoc($productsResult)) {
                        $productId = $product['product_id'];
                        $productQuantity = $product['quantity'];

                        // Update the quantity in the stock_list table
                        $updateStockSql = "UPDATE stock_list SET quantity = quantity + $productQuantity WHERE product_id = $productId";
                        mysqli_query($conn, $updateStockSql);
                    }

                    $userId = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

                    $notificationMessage = "Your order has been canceled successfully.";
                    $notificationType = "Cancellation";
                    $timestamp = date('Y-m-d H:i:s');
                    $status = "new";
                    $isRead = 0;
                    $promoId = 0; // Replace this with the actual promo ID if applicable

                    $insertNotificationSql = "INSERT INTO notifications (user_id, message, notification_type, timestamp, status, is_read, promo_id) 
                                            VALUES ($userId, '$notificationMessage', '$notificationType', '$timestamp', '$status', $isRead, $promoId)";

                    mysqli_query($conn, $insertNotificationSql);

                    // Redirect to the desired page after canceling the order
                    header('location: ' . SITEURL . 'admin/orders.php');
                } else {
                    // Redirect to the desired page if the status is not 'Cancelled'
                    header('location: ' . SITEURL . 'admin/orders.php');
                }
            } else {
                $_SESSION['add'] = "Failed to update status.";
                header('location: ' . SITEURL . 'admin/orders.php');
            }
        }
        ?>
    </div>
</div>

<?php include('partials/footer.php'); ?>
