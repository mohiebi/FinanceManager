FROM node:24-alpine AS build
WORKDIR /service
COPY package.json pnpm-lock.yaml ./
RUN corepack enable && pnpm install --frozen-lockfile --ignore-scripts
COPY tsconfig.json ./
COPY src ./src
RUN pnpm run build && pnpm prune --prod

FROM node:24-alpine
RUN addgroup -S signer \
    && adduser -S -G signer -u 10001 signer \
    && mkdir -p /data /operations \
    && chown -R signer:signer /data /operations
WORKDIR /service
ENV NODE_ENV=production NODE_OPTIONS=--disable-proto=throw
COPY --from=build --chown=signer:signer /service/package.json ./
COPY --from=build --chown=signer:signer /service/node_modules ./node_modules
COPY --from=build --chown=signer:signer /service/dist ./dist
USER signer
EXPOSE 8080
CMD ["node", "dist/server.js"]
