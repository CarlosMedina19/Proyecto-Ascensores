# Panel web de SIGMA en producción: se compila con Vite y lo sirve nginx.
FROM node:20-alpine AS compilacion
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# La API queda en el mismo dominio (Caddy envía /api/* al backend).
ARG VITE_API_URL=/api/v1
ENV VITE_API_URL=$VITE_API_URL
# Clave pública de Reverb (WebSocket). Sin ella el panel funciona igual, con recargas periódicas.
ARG VITE_REVERB_KEY=
ENV VITE_REVERB_KEY=$VITE_REVERB_KEY
RUN npm run build

FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=compilacion /app/dist /usr/share/nginx/html
EXPOSE 80
