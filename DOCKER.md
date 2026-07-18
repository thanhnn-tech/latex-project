# Hướng dẫn chạy Backend qua Docker

Tài liệu này hướng dẫn cách chạy backend Laravel 12 của dự án **OpenLaTeX Workspace** trong môi trường Docker. TeX Live được cài thẳng vào container backend, Laravel gọi `latexmk`/`synctex` trực tiếp trong tiến trình của nó (không dùng Docker-out-of-Docker/sibling container nữa).

---

## 🛠️ Yêu cầu hệ thống
Chỉ cần **Docker** và **Docker Compose** trên máy host — không cần pull thêm image LaTeX nào, vì TeX Live đã được cài sẵn trong `backend/Dockerfile`.

---

## 🚀 Cách chạy nhanh (Out of the box)

Đứng tại thư mục gốc của dự án (`latex-project`) và chạy:

```bash
docker compose up -d --build
```

Lệnh này sẽ:
1. Build image cho PHP container (`backend-app` và `backend-worker`) với PHP 8.4 + TeX Live (latexmk, beamer, pgf/tikz, koma-script,...).
2. Khởi động PostgreSQL (`db`) và Nginx tại port `8000`.
3. Tự động kiểm tra file `.env` (nếu chưa có sẽ tự tạo từ `.env.example`).

Khi khởi động xong, API backend sẽ chạy tại địa chỉ: **`http://localhost:8000`**. Chạy migrate thủ công nếu cần:
```bash
docker compose exec backend-app php artisan migrate --force
```

---

## 📦 Cấu trúc các file cấu hình Docker đã tạo

1. **`docker-compose.yml`** (gốc dự án): Quản lý các service:
   - `backend-app`: Chạy Laravel PHP-FPM (bao gồm cả việc compile LaTeX bằng `latexmk`).
   - `backend-worker`: Chạy queue worker xử lý các tác vụ nền.
   - `backend-nginx`: Web server Nginx định tuyến các request đến PHP-FPM (Chạy ở port `8000`).
   - `db`: Database PostgreSQL (được cấu hình sẵn làm database mặc định).
2. **`backend/Dockerfile`**: Image dùng cho local dev (`backend-app`/`backend-worker`) và cho service worker trên Render — PHP-FPM + TeX Live, không có nginx.
3. **`backend/Dockerfile.render`**: Image riêng cho Render — gộp thêm nginx + supervisord trong cùng container (Render không cho 2 service share filesystem, xem phần Render bên dưới).
4. **`backend/docker/entrypoint.sh`**: Entrypoint cho local dev (chmod storage, tạo `.env` nếu thiếu).
5. **`backend/docker/nginx.conf.template`**: Nginx conf cho local dev, forward PHP request sang service `backend-app` riêng qua `${BACKEND_HOST}:9000`.

---

## 💡 Biên dịch LaTeX

`App\Services\CompileService` và `App\Services\SyncTexService` gọi trực tiếp `latexmk`/`synctex` (qua Symfony `Process`) với working directory là thư mục file của project — không còn spawn container `docker run` riêng, nên không cần mount `/var/run/docker.sock` hay path-alignment `${PWD}` nữa.

⚠️ **Đánh đổi:** trước đây mỗi lần compile chạy trong container `docker run --network none --memory 512m --pids-limit 128 --read-only ...` — cô lập hoàn toàn khỏi container chính. Giờ `latexmk` chạy chung tiến trình với app nên **mất isolation đó** (không giới hạn network/memory/pids per-compile). Rủi ro được giảm bớt bằng các flag sẵn có (`-no-shell-escape`, `-halt-on-error`, timeout 45s), nhưng một file `.tex` cố tình độc hại/ăn nhiều RAM vẫn có thể ảnh hưởng đến cả container. Cân nhắc thêm giới hạn ở tầng ứng dụng (rate-limit, giới hạn kích thước file) nếu cần siết chặt hơn.

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

---

## ☁️ Deploy lên Render

Repo có sẵn `render.yaml` (Blueprint) ở thư mục gốc, định nghĩa:

- **`latex-db`**: PostgreSQL managed database của Render (không tự host bằng container).
- **`backend-web`** (Web Service, public): dùng `backend/Dockerfile.render` — nginx + php-fpm + TeX Live gộp chung 1 container (bắt buộc trên Render vì không share filesystem được giữa 2 service khác nhau). Có gắn **Render Disk** tại `storage/app/private` để file project (.tex/.pdf) không mất khi redeploy — do đó service này chỉ chạy được **1 instance**, không horizontal-scale được ở dạng hiện tại.
- **`backend-worker`** (Private Service/worker): dùng `backend/Dockerfile` (không cần nginx/disk vì không xử lý file project).

**Không dùng `Dockerfile.latex`** để deploy — file đó chỉ build image LaTeX compiler cũ dùng cho cơ chế Docker-out-of-Docker, giờ đã lỗi thời (có thể xoá nếu không còn cần tham khảo).

### Các bước deploy
1. Push repo lên GitHub/GitLab.
2. Trên Render Dashboard → **New → Blueprint**, chọn repo này, Render sẽ đọc `render.yaml` và tạo cả 3 resource ở trên.
3. Kiểm tra lại `plan`/`region` trong `render.yaml` trước khi confirm (giá & tên plan có thể đã đổi so với lúc viết file này).
4. `APP_KEY` được Render tự sinh (`generateValue: true`) cho `backend-web` và worker dùng lại đúng key đó (`fromService.envVarKey`) — không cần set tay.
5. Sau khi deploy xong, `preDeployCommand: php artisan migrate --force` sẽ tự chạy migration trước khi service nhận traffic.

### Giới hạn cần biết
- Container `backend-web` chạy compile LaTeX trực tiếp trong tiến trình app (xem phần "Biên dịch LaTeX" ở trên) — nên chọn plan đủ RAM (khuyến nghị ≥ 2GB) để tránh 1 file `.tex` nặng làm crash cả web service.
- Muốn scale nhiều instance thật sự (tách file storage khỏi Render Disk) thì cần chuyển `config/filesystems.php` disk `local` sang `s3` (driver `s3` đã có sẵn, chỉ cần set các biến `AWS_*`).
