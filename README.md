# OpenLaTeX Workspace

A multi-project LaTeX IDE with **real** LaTeX compilation — no mocks, no
placeholders. Vue 3 frontend, Laravel 12 API, `latexmk`/`pdflatex` running
inside a sandboxed, resource-capped Docker container, PDF.js for a real
rendered preview. Inspired by VS Code / Cursor, not an Overleaf clone.

Workflow: create or import a project → edit `.tex` in Monaco → it
autosaves and auto-compiles ~1s after you stop typing → a real PDF renders
in the preview panel → errors show up in a Problems panel with a real line
number, click one to jump the editor there.

## Tech stack

- **Frontend**: Vue 3 (Composition API) + Vite + TypeScript (strict) + Vue
  Router + Pinia + Tailwind CSS + Monaco Editor + `pdfjs-dist` + `@heroicons/vue`.
- **Backend**: Laravel 12, SQLite, REST API (`backend/`).
- **Compile engine**: TeX Live (`texlive/texlive` Docker image) via
  `latexmk`, invoked per-request through a sandboxed `docker run`.

## Running it (two servers)

**1. Backend** — requires PHP 8.2+, Composer, and Docker running locally
with the `texlive/texlive:latest` image pulled (`docker pull texlive/texlive`,
~2.7GB):

```bash
cd backend
composer install       # first time only
php artisan migrate    # first time only (creates database/database.sqlite)
php artisan serve      # http://localhost:8000
```

**2. Frontend**, from the repo root:

```bash
npm install
npm run dev            # http://localhost:5173
```

Open `http://localhost:5173`. The frontend talks to the API at
`http://localhost:8000/api` by default — override with `VITE_API_BASE_URL`
if the backend runs elsewhere.

```bash
npm run build           # type-check (vue-tsc) + production build to dist/
npm run preview         # preview the production build locally
```

## Using the app

1. **Dashboard** (`/`): create a blank project, start from a template
   (Blank Article / Simple Report), or import an existing `.md`/`.tex` file.
   Importing a `.md` runs it through a real Markdown→LaTeX converter and
   keeps both the source and the generated `main.tex`; a `.tex` is imported
   as-is. Projects and their files live server-side (SQLite + disk), not in
   browser storage.
2. Opening or creating a project takes you to `/workspace/:projectId` — an
   Explorer sidebar, a tabbed Monaco editor, a real PDF preview on the right,
   and a Problems / Compile Log panel at the bottom.
3. Edit any open tab — content autosaves, and ~1s after you stop typing the
   file is saved and sent to the real compiler. The preview header shows
   Idle / Compiling / Compiled / Compile failed (+ duration).
4. If compilation fails, the **Problems** tab lists real errors/warnings
   parsed from the `pdflatex` log (with line numbers where available) —
   click one to jump the editor to that line. **Compile Log** shows the raw
   `latexmk` output.
5. Zoom and page navigation in the preview are real (backed by `pdfjs-dist`
   against the actual rendered PDF), plus a Refresh button.
6. Download the active file, or the whole project as a `.zip` (bundles the
   compiled `main.pdf` too, if one exists) — both come from the backend.
7. Back on the Dashboard, projects can be searched, reopened, or deleted, and
   a `.zip` can be imported directly as a new project (folders and all).

The Explorer/Outline/Search/Symbols/Citations icon rail on the left of the
workspace matches the target IDE shell — only Explorer is functional right
now; the others are visibly disabled with a "coming in a later phase" tooltip.

### Asset management (Explorer)

- **Folders**: nested tree, "+ Folder" to create one, drag a file onto a
  folder row to move it in.
- **Upload**: the "Upload" button accepts images (png/jpg/jpeg/svg/webp/gif),
  documents (pdf/bib/tex/sty/cls/bst), and data files (csv/json/xml/txt) —
  binary content is stored and served as-is, never forced through text
  decoding.
- **Right-click** any file/folder for Rename, Duplicate, Copy Path, Download,
  Properties (kind/size/created/updated), and Delete (folders cascade).
- **Images**: click one for a preview modal with real width/height (read
  client-side from the decoded image), size, format, and created time. Drag
  an image from the Explorer and drop it into the editor to insert
  `\includegraphics[width=\textwidth]{path}` at the drop position — no
  typing required.
- **PDFs** open in a new browser tab (native PDF rendering); text-like
  assets (`.bib`, `.sty`, `.cls`, `.bst`, `.csv`, `.json`, `.xml`, `.txt`)
  open as a normal Monaco tab.

## Compile security

Each compile runs in an ephemeral, network-isolated container, not on the
host:

```
docker run --rm --network none --cpus 1 --memory 512m --memory-swap 512m \
  --pids-limit 128 --read-only --tmpfs /tmp \
  -v <project files dir>:/data -w /data \
  texlive/texlive:latest \
  latexmk -pdf -interaction=nonstopmode -halt-on-error -no-shell-escape -jobname=main main.tex
```

`-no-shell-escape` blocks `\write18`-style command execution from LaTeX
source; `--network none` blocks any network access from inside the
container; CPU/memory/PID limits and a 45s host-side timeout (which force-
removes the container on expiry) bound resource usage. The `docker run`
argument list is built as an array (Symfony `Process`), never a shell
string, so there's no shell-injection surface from project/file names.

## Architecture

```
src/                            # Vue frontend
├── components/
│   ├── dashboard/               # ProjectCard, CreateProjectPanel
│   ├── workspace/               # ActivityBar, EditorTabs, BottomPanel, ContextMenu,
│   │                            # PropertiesModal, ImagePreviewModal
│   ├── editor/                  # FileExplorer, FileTreeNode, LatexEditor, PreviewPanel
│   └── upload/                  # FileUploader
├── views/                       # DashboardView, WorkspaceView
├── layouts/                     # WorkspaceLayout
├── stores/                      # projects.store (dashboard), workspace.store (open project, tabs, files, compile state)
├── services/
│   ├── api/httpClient.ts        # fetch wrapper for the Laravel API (incl. multipart uploads)
│   ├── storage/                 # HttpProjectStorageService (project CRUD)
│   └── converter/                # real Markdown→LaTeX parser (Import Project)
├── composables/                 # useFileUpload, useAutosave, useAutoCompile
├── constants/                   # templates.ts
├── utils/                       # buildFileTree, fileKind (icons), asset/file validation,
│                                # download, Monaco/PDF.js worker setup
└── types/

backend/                         # Laravel 12 API
├── app/Models/                  # Project, ProjectFile (path + kind + is_directory), Problem
├── app/Services/
│   ├── ProjectFileStorage.php   # disk I/O (storage/app/private/projects/{id}/...), text + binary
│   ├── CompileService.php       # docker run orchestration (Symfony Process)
│   └── CompileLogParser.php     # regex parser: pdflatex/latexmk log → Problem rows
├── app/Support/
│   ├── PathValidator.php        # per-segment path safety (create/upload/move/folder)
│   └── ProjectFilePresenter.php  # shared file→JSON shape (used by both controllers)
├── app/Http/Controllers/Api/    # ProjectController, ProjectFileController, CompileController
└── routes/api.php
```

File content lives on disk (`storage/app/private/projects/{id}/files/`), not
in the database — a compile needs a real file tree to mount into the
container anyway, so the DB only tracks file metadata. A `ProjectFile.name`
is a full relative path (e.g. `images/logo.png`); folders are rows with
`is_directory = true`, though a folder implied purely by a descendant file's
path (no explicit row) is also handled — the frontend's `buildFileTree`
synthesizes those on the fly.

## Known limitations (current scope)

- Only `main.tex` is compiled — no `\include`d chapter graph beyond what
  `pdflatex` resolves itself; still one compile target per project.
- Compile runs synchronously inside the HTTP request (no job queue) — fine
  for local single-user use, not for concurrent multi-user load.
- Open tabs aren't persisted across a reload (reopening a project starts
  with just the last-active file as the only open tab).
- Compile log parsing is regex-based against common `pdflatex` patterns, not
  a full TeX log grammar — most errors/warnings get a line number, some don't.
- No dedicated Assets panel yet (Explorer's file tree covers search-free
  browsing/preview/upload) — upload is one file at a time, no progress
  queue/cancel/retry/paste-image yet, no BibTeX-aware citation tooling, no
  Image Insert Wizard modal. All explicitly deferred to a later pass.
- No auth — anyone who can reach the API can read/write/delete any project.
  Fine for local dev, not for deploying this as-is.

## Roadmap

- **Phase 2 (in progress)** — Asset Manager: nested folders, multi-type
  upload, context menu, image preview, drag-to-insert, ZIP import/export
  (done); dedicated Assets panel, upload queue, Image Insert Wizard, BibTeX
  Manager (next); authentication, cloud storage.
- **Phase 3** — AI Panel (mock UI first, then real); async compile queue,
  multi-user.
- **Phase 4** — Citation Manager; AI Assistant, real-time collaboration, Git
  integration, version history.
- **Phase 5** — Project Settings (theme, compiler, packages, language).
