<?php
include 'connect.php';
include 'session_start.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
  echo "<script>alert('유효하지 않은 주문입니다.'); history.back();</script>";
  exit;
}

// 주문 조회
$order_sql = "
    SELECT id, user_id, status, address, used_point
    FROM orders
    WHERE id = $order_id
";
$order_result = $conn->query($order_sql);

if ($order_result && $order_result->num_rows > 0) {
    $order_row = $order_result->fetch_assoc();
    $user_id = $order_row['user_id'];
    $status = $order_row['status'];
    $address = $order_row['address'];
    $used_point = $order_row['used_point'];
} else {
    echo "<script>alert('존재하지 않는 주문입니다.'); history.back();</script>";
    exit;
}

// 주문 취소
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
    $conn->query("DELETE FROM orders WHERE id = $order_id");

    // 포인트 환불 처리 (상태 체크 없이 무조건 실행)
    if ($used_point > 0) {
      $conn->query("UPDATE users SET point = point + $used_point WHERE id = $user_id");
  }
    echo "<script>alert('주문이 취소되었습니다.'); location.href='mypage.php';</script>";
    exit;
}

// 주문 상품 조회
$sql = "
    SELECT oi.id AS item_id, b.title, b.price, b.image_path, oi.quantity
    FROM order_items oi
    JOIN books b ON oi.book_id = b.id
    WHERE oi.order_id = $order_id
";
$result = $conn->query($sql);
$total_price = 0;
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>주문 상세 - 온라인 서점</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="css/order_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  <?php include 'header.php'; ?>
  <main>
    <div class="order-detail-wrapper">
      <h2>주문 상세</h2>

      <div class="status-box">
        배송지: <?= $address ?><br>
        결제 상태:
        <?php if ($status === 'paid'): ?>
          <span class="status-paid">결제 완료</span>
        <?php elseif ($status === 'pending'): ?>
          <span class="status-pending">결제 대기중</span>
        <?php else: ?>
          <span><?= $status ?></span>
        <?php endif; ?>
      </div>

      <form method="post" onsubmit="return confirm('정말 주문을 취소하시겠습니까?');">
        <input type="hidden" name="cancel_order" value="1">
        <button type="submit" class="cancel-btn">주문 전체 취소<?php if ($used_point > 0): ?> 및 환불<?php endif; ?></button>
      </form>

      <div class="cart-container" style="margin-top:30px;">
        <table class="cart-table">
          <thead>
            <tr>
              <th style="width:50%;">상품정보</th>
              <th style="width:15%;">수량</th>
              <th style="width:15%;">상품금액</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()):
              $item_total = $row['price'] * $row['quantity'];
              $total_price += $item_total;
            ?>
            <tr>
              <td class="cart-product-info">
                <img src="<?= $row['image_path'] ?? 'images/default.jpg' ?>" alt="표지">
                <div class="cart-info-detail">
                  <span class="cart-title">[도서] <?= $row['title'] ?></span>
                  <div class="cart-meta">
                    <span class="cart-price-sale"><?= $row['price'] ?>원</span>
                  </div>
                </div>
              </td>
              <td><?= $row['quantity'] ?>권</td>
              <td><?= $item_total ?>원</td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

        <div class="cart-summary">
          <div class="summary-item total">
            <span>총 결제 금액</span>
            <?= $total_price ?>원
          </div>
        </div>
      </div>
    </div>
  </main>
  <?php include 'footer.php'; ?>
</body>
</html>