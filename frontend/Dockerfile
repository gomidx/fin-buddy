FROM node:20-alpine

WORKDIR /app

# Copia arquivos de dependências
COPY package.json package-lock.json* ./

# Instala dependências
RUN npm install

# Copia o restante do código
COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev"]
