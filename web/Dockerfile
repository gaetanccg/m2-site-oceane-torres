# ===========================================
# Stage 1: Development
# ===========================================
FROM node:20-alpine AS development

WORKDIR /app

# Copier les fichiers de dépendances
COPY package*.json ./

# Installer les dépendances
RUN npm install

# Copier le code source
COPY . .

# Exposer le port Vite
EXPOSE 5173

# Démarrer le serveur de développement
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

# ===========================================
# Stage 2: Build pour production
# ===========================================
FROM node:20-alpine AS builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .

# Build de production
RUN npm run build

# ===========================================
# Stage 3: Production avec Nginx
# ===========================================
FROM nginx:alpine AS production

# Copier la config Nginx personnalisée
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Copier les fichiers buildés
COPY --from=builder /app/dist /usr/share/nginx/html

# Exposer le port
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
