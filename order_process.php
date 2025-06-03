<?php
require_once 'connect.php';
require_once 'session_start.php';

$user_id = $_SESSION['user_id']; // 세션 검증 안 함!

// 입력 검증 없이 직접 변수 할당
$recipient = $_POST['recipient'];
$phone = $_POST['phone1'] . '-' . $_POST['phone2'] . '-' . $_POST['phone3'];
$postcode = $_POST['postcode'];
$road = $_POST['road_address'];
$detail = $_POST['detail_address'];
$address = "($postcode) $road $detail";
$total_price = isset($_POST['total_price']) ? (float)$_POST['total_price'] : 0;
$used_point = isset($_POST['used_point']) ? (int)$_POST['used_point'] : 0;

// 직접 쿼리로 작성 (SQL 인젝션 가능!)
$sql = "SELECT c.book_id, c.quantity, b.price FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = $user_id";
$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $book_id = $row['book_id'];
    $quantity = $row['quantity'];
    $price = $row['price'];
    $items[$book_id] = ['quantity' => $quantity, 'price' => $price];
    $total_price += $price * $quantity;
}

// 직접 구매 처리 (쿼리도 마찬가지로 취약!)
if (isset($_POST['direct_buy'], $_POST['book_id'], $_POST['quantity'])) {
    $book_id = $_POST['book_id'];
    $quantity = $_POST['quantity'];
    $sql = "SELECT price FROM books WHERE id = '$book_id'";
    $res = $conn->query($sql);
    if ($book = $res->fetch_assoc()) {
        $price = $book['price'];
        if (isset($items[$book_id])) {
            $items[$book_id]['quantity'] += $quantity;
        } else {
            $items[$book_id] = ['quantity' => $quantity, 'price' => $price];
        }
        $total_price += $price * $quantity;
    }
}

if (empty($items)) {
    echo "<script>alert('주문할 항목이 없습니다.'); history.back();</script>";
    exit;
}

// 포인트 확인
$sql = "SELECT point FROM users WHERE id = $user_id";
$res = $conn->query($sql);
$user = $res->fetch_assoc();
if ($user['point'] < $total_price) {
    echo "<script>alert('포인트 부족!'); history.back();</script>";
    exit;
}

// 주문번호 생성
$sql = "SELECT MAX(order_seq) FROM orders WHERE user_id = $user_id";
$res = $conn->query($sql);
$row = $res->fetch_row();
$order_seq = $row[0] + 1;

// 주문 저장
$sql = "INSERT INTO orders (user_id, order_seq, recipient, phone, address, total_price, payment_method, used_point, status, created_at)
        VALUES ($user_id, $order_seq, '$recipient', '$phone', '$address', $total_price, 'point', $total_price, 'paid', NOW())";
$conn->query($sql);
$order_id = $conn->insert_id;

// 주문 상세 저장
foreach ($items as $book_id => $info) {
    $quantity = $info['quantity'];
    $price = $info['price'];
    $sql = "INSERT INTO order_items (order_id, book_id, quantity, price) VALUES ($order_id, '$book_id', $quantity, $price)";
    $conn->query($sql);
}

// 포인트 차감
$sql = "UPDATE users SET point = point - $total_price WHERE id = $user_id";
$conn->query($sql);

// 장바구니 비우기
$sql = "DELETE FROM cart WHERE user_id = $user_id";
$conn->query($sql);

echo "<script>alert('주문 완료!'); location.href='order_complete.php';</script>";
?>