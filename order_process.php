<?php
require_once 'connect.php';
require_once 'session_start.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<script>alert('로그인이 필요합니다.'); location.href='login.php';</script>";
    exit;
}

$recipient = strip_tags(trim($_POST['recipient']));
$phone = $_POST['phone1'] . '-' . $_POST['phone2'] . '-' . $_POST['phone3'];
$postcode = strip_tags(trim($_POST['postcode']));
$road = strip_tags(trim($_POST['road_address']));
$detail = strip_tags(trim($_POST['detail_address']));
$address = "($postcode) $road $detail";

$items = [];
$total_price = 0;

$conn->begin_transaction();

try {
    // 장바구니 항목 불러오기
    $sql = "SELECT c.book_id, c.quantity, b.price FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = $user_id";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $book_id = $row['book_id'];
        $quantity = $row['quantity'];
        $price = $row['price'];

        $items[$book_id] = ['quantity' => $quantity, 'price' => $price];
        $total_price += $price * $quantity;
    }

    // 바로 구매 처리
    if (isset($_POST['direct_buy'], $_POST['book_id'], $_POST['quantity'])) {
        $book_id = $_POST['book_id'];
        $quantity = intval($_POST['quantity']);

        $price_sql = "SELECT price FROM books WHERE id = '$book_id'";
        $price_result = $conn->query($price_sql);
        if ($book = $price_result->fetch_assoc()) {
            $price = $book['price'];
            if (isset($items[$book_id])) {
                $items[$book_id]['quantity'] += $quantity;
            } else {
                $items[$book_id] = ['quantity' => $quantity, 'price' => $price];
            }
            $total_price += $price * $quantity;
        }
    }

    // 주문 번호 생성
    $seq_sql = "SELECT MAX(order_seq) as max_seq FROM orders WHERE user_id = $user_id";
    $seq_result = $conn->query($seq_sql);
    $row = $seq_result->fetch_assoc();
    $order_seq = ($row['max_seq'] ?? 0) + 1;

    // 주문 저장
    $order_sql = "INSERT INTO orders (user_id, order_seq, recipient, phone, address, total_price, created_at, status)
                  VALUES ($user_id, $order_seq, '$recipient', '$phone', '$address', $total_price, NOW(), 'pending')";
    $conn->query($order_sql);
    $order_id = $conn->insert_id;

    // 토큰 저장
    $token = bin2hex(random_bytes(32));
    echo "<script>console.log('Token generated: $token');</script>";
    $conn->query("UPDATE orders SET token = '$token' WHERE id = $order_id");

    // 주문 상세 저장
    foreach ($items as $book_id => $info) {
        $quantity = $info['quantity'];
        $price = $info['price'];
        $conn->query("INSERT INTO order_items (order_id, book_id, quantity, price)
                      VALUES ($order_id, '$book_id', $quantity, $price)");
    }

    // 장바구니 비우기
    $conn->query("DELETE FROM cart WHERE user_id = $user_id");

    $conn->commit();
    echo "<script>alert('주문이 완료되었습니다.'); location.href='order_complete.php?token=" . urlencode($token) . "';</script>";
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("주문 처리 실패: " . $e->getMessage());
    echo "<script>alert('주문 처리 중 오류가 발생했습니다.'); location.href='cart.php';</script>";
    exit;
}
?>
