# Hướng dẫn chạy Backend qua Docker

Tài liệu này hướng dẫn cách chạy backend Laravel 12 của dự án **OpenLaTeX Workspace** trong môi trường Docker, hỗ trợ việc biên dịch LaTeX trực tiếp (Docker-out-of-Docker).

---

## 🛠️ Yêu cầu hệ thống
1. Đã cài đặt **Docker** và **Docker Compose** trên máy host.
2. Đã pull image LaTeX gọn nhẹ về máy host (~500MB thay vì 2.7GB):
   ```bash
   docker pull kjarosh/latex:medium
   ```

---

## 🚀 Cách chạy nhanh (Out of the box)

Thư mục dự án đã được thiết lập mặc định sử dụng cơ sở dữ liệu **SQLite**. Bạn chỉ cần đứng tại thư mục gốc của dự án (`latex-project`) và chạy lệnh sau:

```bash
docker compose up -d --build
```

Lệnh này sẽ:
1. Build image cho PHP container (`backend-app` và `backend-worker`) với PHP 8.3.
2. Khởi động Nginx tại port `8000`.
3. Tự động kiểm tra file `.env` (nếu chưa có sẽ tự tạo từ `.env.example`).
4. Chạy migration tạo bảng cho SQLite database.
5. Cấu hình phân quyền động để PHP container gọi được daemon Docker của máy host để compile.

Khi khởi động xong, API backend sẽ chạy tại địa chỉ: **`http://localhost:8000`**

---

## 📦 Cấu trúc các file cấu hình Docker đã tạo

1. **`docker-compose.yml`** (gốc dự án): Quản lý các service:
   - `backend-app`: Chạy Laravel PHP-FPM.
   - `backend-worker`: Chạy queue worker xử lý các tác vụ nền.
   - `backend-nginx`: Web server Nginx định tuyến các request đến PHP-FPM (Chạy ở port `8000`).
   - `db`: Database PostgreSQL (được cấu hình sẵn làm database mặc định).
2. **`backend/Dockerfile`**: Build PHP 8.3 container cài sẵn các extension cần thiết (PDO, Zip, GD,...) và cài thêm `docker-cli` để Laravel có thể ra lệnh compile.
3. **`backend/docker/entrypoint.sh`**: File entrypoint tự động phân quyền truy cập `/var/run/docker.sock` cho user `www-data` bên trong container.
4. **`backend/docker/nginx.conf.template`**: Cấu hình Nginx linh hoạt dùng các biến môi trường để trỏ đúng thư mục public của Laravel.

---

## 💡 Cơ chế Biên dịch LaTeX trong Docker (Docker-out-of-Docker)

Khi người dùng chỉnh sửa file `.tex` ở frontend, Laravel backend sẽ kích hoạt lệnh compile bằng cách chạy:
```bash
docker run --rm -v <đường_dẫn_dự_án>:/data kjarosh/latex:medium ...
```
*(Bạn có thể cấu hình thay đổi image này bằng biến môi trường `LATEX_COMPILER_IMAGE` trong file cấu hình)*

Để cơ chế này hoạt động chính xác khi bản thân Laravel cũng đang chạy trong Docker:
1. **Mount `/var/run/docker.sock`**: File socket của Docker máy host được mount vào container backend để gửi lệnh trực tiếp ra ngoài.
2. **Path-Alignment (Đồng bộ đường dẫn)**: Docker host không thể hiểu đường dẫn ảo bên trong container (ví dụ `/var/web`). Vì vậy, `docker-compose.yml` đã ánh xạ thư mục backend theo đúng đường dẫn tuyệt đối của máy host (sử dụng biến môi trường `${PWD}`).
   - Host: `/Users/.../latex-project/backend`
   - Container: `/Users/.../latex-project/backend`
   Như vậy, đường dẫn truyền từ Laravel ra host Docker daemon sẽ trùng khớp hoàn toàn, đảm bảo mount volume biên dịch LaTeX không bị lỗi trống thư mục.

---

## 🗄️ Database PostgreSQL

Database mặc định của dự án là PostgreSQL (được định nghĩa trong service `db` của `docker-compose.yml`). Cấu hình `.env` của backend (`backend/.env`) đã được thiết lập sẵn như sau:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=db
   DB_PORT=5432
   DB_DATABASE=latex
   DB_USERNAME=latex_user
   DB_PASSWORD=latex_password
   ```
Nếu bạn vừa đổi cấu hình này, khởi động lại docker-compose:
   ```bash
   docker compose down
   docker compose up -d
   ```
4. Chạy migrate thủ công (nếu cần):
   ```bash
   docker compose exec backend-app php artisan migrate --force
   ```

---

## 🔍 Một số lệnh hữu ích

- **Xem log của ứng dụng:**
  ```bash
  docker compose logs -f backend-app
  ```
- **Truy cập vào terminal bên trong backend:**
  ```bash
  docker compose exec backend-app sh
  ```
- **Xóa toàn bộ container và volume dữ liệu:**
  ```bash
  docker compose down -v
  ```
