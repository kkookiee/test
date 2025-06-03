<?php
require_once 'connect.php';
require_once 'session_start.php';

$user_id = $_SESSION['user_id']; // 세션 검증 없음

// 클라이언트 조작값 그대로 사용 (취약)
$recipient = $_POST['recipient'];
$phone = $_POST['phone1'] . '-' . $_POST['phone2'] . '-' . $_POST['phone3'];
$postcode = $_POST['postcode'];
$road = $_POST['road_address'];
$detail = $_POST['detail_address'];
$address = "($postcode) $road $detail";

$total_price = isset($_POST['total_price']) ? (int)$_POST['total_price'] : 0;
$used_point = isset($_POST['used_point']) ? (int)$_POST['used_point'] : 0;

// 장바구니 기반 항목 불러오되, 가격 누적은 하지 않음
$sql = "SELECT c.book_id, c.quantity, b.price FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = $user_id";
$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $book_id = $row['book_id'];
    $items[$book_id] = [
        'quantity' => $row['quantity'],
        'price' => $row['price']  // 사용은 안 하지만 DB 넣기 위해 유지
    ];
}

// 장바구니 비었을 경우 처리
if (empty($items)) {
    echo "<script>alert('주문할 항목이 없습니다.'); history.back();</script>";
    exit;
}

// 보유 포인트 확인 (하지만 사용 포인트는 클라이언트 값 신뢰)
$sql = "SELECT point FROM users WHERE id = $user_id";
$res = $conn->query($sql);
$user = $res->fetch_assoc();
if ($user['point'] < $used_point) {
    echo "<script>alert('포인트 부족!'); history.back();</script>";
    exit;
}

// 주문번호 생성
$sql = "SELECT MAX(order_seq) FROM orders WHERE user_id = $user_id";
$res = $conn->query($sql);
$row = $res->fetch_row();
$order_seq = ($row[0] ?? 0) + 1;

// 주문 저장 (조작된 결제 금액 사용)
$sql = "INSERT INTO orders (user_id, order_seq, recipient, phone, address, total_price, payment_method, used_point, status, created_at)
        VALUES ($user_id, $order_seq, '$recipient', '$phone', '$address', $total_price, 'point', $used_point, 'paid', NOW())";
$conn->query($sql);
$order_id = $conn->insert_id;

// 주문 상세 저장 (상품은 원래대로)
foreach ($items as $book_id => $info) {
    $quantity = $info['quantity'];
    $price = $info['price'];
    $sql = "INSERT INTO order_items (order_id, book_id, quantity, price)
            VALUES ($order_id, '$book_id', $quantity, $price)";
    $conn->query($sql);
}

// 포인트 차감 (조작된 사용 포인트 기준)
$sql = "UPDATE users SET point = point - $used_point WHERE id = $user_id";
$conn->query($sql);

// 장바구니 비우기
$conn->query("DELETE FROM cart WHERE user_id = $user_id");

echo "<script>alert('주문 완료!'); location.href='order_complete.php';</script>";
?>
