using Microsoft.AspNetCore.Builder;
using Microsoft.EntityFrameworkCore;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddDbContext<AppDbContext>(options =>
    options.UseInMemoryDatabase("LivrariaDb"));

builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

var app = builder.Build();

app.UseSwagger();
app.UseSwaggerUI(c =>
{
    c.RoutePrefix = string.Empty;
});

app.MapPost("/livros", async (Livro livro, AppDbContext db) =>
{
    db.Livros.Add(livro);
    await db.SaveChangesAsync();
    return Results.Created($"/livros/{livro.Id}", livro);
});

app.MapGet("/livros", async (AppDbContext db) =>
{
    return Results.Ok(await db.Livros.ToListAsync());
});

app.MapGet("/livros/{id}", async (int id, AppDbContext db) =>
{
    var livro = await db.Livros.FindAsync(id);
    return livro is not null
        ? Results.Ok(livro)
        : Results.NotFound("Livro não encontrado.");
});

app.MapPut("/livros/{id}", async (int id, Livro livroAtualizado, AppDbContext db) =>
{
    var livro = await db.Livros.FindAsync(id);
    if (livro is null) return Results.NotFound("Livro não encontrado.");

    livro.Titulo = livroAtualizado.Titulo;
    livro.Autor = livroAtualizado.Autor;
    livro.DataPublicacao = livroAtualizado.DataPublicacao;

    await db.SaveChangesAsync();
    return Results.Ok(livro);
});

app.MapDelete("/livros/{id}", async (int id, AppDbContext db) =>
{
    var livro = await db.Livros.FindAsync(id);
    if (livro is null) return Results.NotFound("Livro não encontrado.");

    db.Livros.Remove(livro);
    await db.SaveChangesAsync();

    return Results.Ok("Livro removido com sucesso.");
});

app.Run();
