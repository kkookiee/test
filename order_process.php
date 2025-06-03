<?php
require_once 'connect.php';
require_once 'session_start.php';

// 세션 확인
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인이 필요합니다.'); location.href='login.php';</script>";
    exit;
}
$user_id = $_SESSION['user_id'];

// 클라이언트에서 받은 모든 값 신뢰 (취약!)
$recipient = $_POST['recipient'];
$phone = $_POST['phone1'] . '-' . $_POST['phone2'] . '-' . $_POST['phone3'];
$postcode = $_POST['postcode'];
$road = $_POST['road_address'];
$detail = $_POST['detail_address'];
$address = "($postcode) $road $detail";

$total_price = $_POST['total_price']; // 클라이언트 조작 가능

// 주문 항목 (직접 전달, 조작 가능)
$book_id = $_POST['book_id'];
$quantity = $_POST['quantity'];
$price = $_POST['price'];

// 조작 가능 항목 구성
$items = [];
$items[$book_id] = ['quantity' => $quantity, 'price' => $price];

// 포인트 확인 (실제 포인트는 확인하지만, 조작된 가격 사용)
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
$order_seq = ($row[0] ?? 0) + 1;

// 주문 저장
$sql = "INSERT INTO orders (user_id, order_seq, recipient, phone, address, total_price, payment_method, used_point, status, created_at)
        VALUES ($user_id, $order_seq, '$recipient', '$phone', '$address', $total_price, 'point', $total_price, 'paid', NOW())";
$conn->query($sql);
$order_id = $conn->insert_id;

// 주문 상세 저장
foreach ($items as $book_id => $info) {
    $quantity = $info['quantity'];
    $price = $info['price'];
    $sql = "INSERT INTO order_items (order_id, book_id, quantity, price) 
            VALUES ($order_id, '$book_id', $quantity, $price)";
    $conn->query($sql);
}

// 포인트 차감
$sql = "UPDATE users SET point = point - $total_price WHERE id = $user_id";
$conn->query($sql);

// 장바구니는 무시하거나 초기화
$conn->query("DELETE FROM cart WHERE user_id = $user_id");

echo "<script>alert('주문 완료!'); location.href='order_complete.php';</script>";
?>