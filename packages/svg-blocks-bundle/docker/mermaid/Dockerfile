# The Mermaid service of phil/svg-blocks-bundle: Node.js, Chromium from Debian, Mermaid's CLI library, and a small
# HTTP server, run as an unprivileged user on a read-only file system. doc/diagrams.md holds its security review.
#
#   docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
#   docker compose -f vendor/phil/svg-blocks-bundle/docker/mermaid/compose.yaml up -d
#
# Pin the base image by its digest where the image is built for production: FROM node:22.11.0-bookworm-slim@sha256:…

FROM node:22.11.0-bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends chromium fonts-dejavu-core fonts-noto-core \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /var/cache/apt/*

# Puppeteer drives the Chromium of Debian, kept up to date by the distribution, and downloads no browser of its own.
# Chromium writes its profile and its caches under HOME, a tmpfs at run time.
ENV PUPPETEER_SKIP_DOWNLOAD=1 \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    NODE_ENV=production \
    HOME=/tmp \
    PORT=3000

WORKDIR /srv/mermaid
COPY package.json ./
# No install script runs: none of the dependencies needs one, and a script is code run at build time.
RUN npm install --omit=dev --ignore-scripts --no-audit --no-fund && npm cache clean --force
COPY lib.mjs server.mjs ./
RUN chown -R root:root /srv/mermaid && chmod -R a-w /srv/mermaid

# The image's `node` user, 1000:1000, owns nothing it runs.
USER 1000:1000
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s CMD node -e "fetch('http://127.0.0.1:3000/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "server.mjs"]
