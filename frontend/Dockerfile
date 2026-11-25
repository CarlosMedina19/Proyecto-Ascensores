# Panel web de SIGMA en producción: se compila con Vite y lo sirve nginx.
FROM node:22-alpine AS compilacion
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
# Clave de Google Maps (navegador) para el mapa de Auditoría. Sin ella se usa el mapa oscuro de respaldo (OpenStreetMap).
ARG VITE_GOOGLE_MAPS_KEY=
ENV VITE_GOOGLE_MAPS_KEY=$VITE_GOOGLE_MAPS_KEY
RUN npm run build

FROM nginx:1.28-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/seguridad.conf /etc/nginx/snippets/seguridad.conf
COPY --from=compilacion /app/dist /usr/share/nginx/html
EXPOSE 80
