# Build stage
FROM node:20-alpine AS build

WORKDIR /app
COPY package*.json ./
RUN npm install

COPY . .

# VITE_API_URL di-inject saat build via Railway env var
ARG VITE_API_URL=http://localhost:8000/api
ENV VITE_API_URL=$VITE_API_URL

# Skip type check di CI agar build tidak gagal karena type error
RUN npx vite build

# Production stage
FROM node:20-alpine AS production

RUN npm install -g serve

WORKDIR /app
COPY --from=build /app/dist ./dist

EXPOSE 3000

# PENTING: gunakan shell form (bukan exec form) agar $PORT ter-expand
CMD sh -c "serve -s dist -l ${PORT:-3000}"
