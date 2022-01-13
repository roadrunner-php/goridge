# Image page: <https://hub.docker.com/_/golang>
FROM golang:1.17-alpine as builder

# Copy server sources into image
COPY ./go.mod ./go.sum ./server.go /src/

WORKDIR /src

# Build goride server
RUN go mod tidy

RUN go mod download

RUN go mod verify

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -a -ldflags="-s" -o ./server ./server.go

# Image page: <https://hub.docker.com/_/alpine>
FROM alpine:3.15 as runtime

# Unprivileged user creation <https://stackoverflow.com/a/55757473/12429735RUN>
RUN adduser \
    --disabled-password \
    --gecos "" \
    --home "/nonexistent" \
    --shell "/sbin/nologin" \
    --no-create-home \
    --uid "10001" \
    "appuser"

# Use an unprivileged user
USER appuser:appuser

# Import from builder
COPY --from=builder /src/server /usr/bin/server

EXPOSE 7079/tcp

ENTRYPOINT ["/usr/bin/server"]
