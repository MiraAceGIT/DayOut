// Sans PHP, le test est ignore, sauf si REQUIRE_CONTRACT=1 (CI) : il echoue alors.
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { describe, it } from "node:test";
import { fileURLToPath } from "node:url";
import { toCategory } from "../src/model/mappers/categoryMapper.js";
import { toEvent, toEventDto } from "../src/model/mappers/event/eventMapper.js";
import { toCreator } from "../src/model/mappers/creatorMapper.js";
import { toSchool } from "../src/model/mappers/schoolMapper.js";
import { toEmailVerification, toRegisteredUser } from "../src/model/mappers/user/userMapper.js";
import { toUserCreateDto } from "../src/model/mappers/user/userRequestMapper.js";

const BACK = fileURLToPath(new URL("../../back/", import.meta.url));
const DTO_FILE = new URL("../src/model/dto/", import.meta.url);

function loadSpec() {
  const php = [process.env.PHP, "php", "C:/xampp/php/php.exe"].filter(Boolean)
    .find((bin) => bin === "php" || existsSync(bin));
  const code = 'require "src/Shared/Yaml.php"; require "src/Docs/OpenApiSpec.php"; echo json_encode(App\\Docs\\OpenApiSpec::load("docs/openapi"));';
  try {
    return JSON.parse(execFileSync(php, ["-r", code], { cwd: BACK, encoding: "utf8" }));
  } catch (error) {
    if (process.env.REQUIRE_CONTRACT === "1") throw error;
    return null;
  }
}

function typedefProperties(file, name) {
  const source = readFileSync(new URL(file, DTO_FILE), "utf8");
  const block = source.match(new RegExp(`@typedef \\{object\\} ${name}\\b([\\s\\S]*?)\\*/`))?.[1] ?? "";
  return [...block.matchAll(/@property \{[^}]+\} (\w+)/g)].map((m) => m[1]);
}

function sample(schema) {
  if (schema.$ref) return {};
  return { integer: 1, number: 1.5, boolean: true, array: [], object: {} }[schema.type] ?? "x";
}

function keysReadBy(fn, dto) {
  const read = new Set();
  fn(new Proxy(dto, { get: (target, key) => (read.add(String(key)), target[key]) }));
  return read;
}

const spec = loadSpec();
const schemas = spec?.components.schemas;
const skip = spec ? false : "PHP introuvable : spec OpenAPI non chargee (definir PHP=... ou REQUIRE_CONTRACT=1)";

describe("contrat avec la spec OpenAPI du back", { skip }, () => {
  it("le typedef EventDto liste exactement les champs du schema Event", () => {
    assert.deepEqual(typedefProperties("eventDto.js", "EventDto"), Object.keys(schemas.Event.properties));
  });

  it("le typedef EventPageDto suit le schema EventPage", () => {
    assert.deepEqual(typedefProperties("eventDto.js", "EventPageDto"), Object.keys(schemas.EventPage.properties));
  });

  it("le typedef CategoryDto suit le schema Category", () => {
    assert.deepEqual(typedefProperties("categoryDto.js", "CategoryDto"), Object.keys(schemas.Category.properties));
  });

  it("le createur de chaque event suit le schema Creator", () => {
    assert.deepEqual(typedefProperties("creatorDto.js", "CreatorDto"), Object.keys(schemas.Creator.properties));
    assert.equal(schemas.Event.properties.creator.$ref, "#/components/schemas/Creator");
    const fields = Object.keys(schemas.Creator.properties);
    const read = keysReadBy(toCreator, Object.fromEntries(fields.map(field => [field, sample(schemas.Creator.properties[field])])));
    assert.deepEqual([...read], fields);
  });

  it("toEvent lit chaque champ du schema Event, et seulement ceux-la", () => {
    const fields = Object.keys(schemas.Event.properties);
    const dto = Object.fromEntries(fields.map((f) => [f, sample(schemas.Event.properties[f])]));
    const read = keysReadBy(toEvent, dto);
    assert.deepEqual(fields.filter((f) => !read.has(f)), [], "champs de l'API ignores par toEvent");
    assert.deepEqual([...read].filter((k) => !fields.includes(k)), [], "toEvent lit un champ absent de l'API");
  });

  it("toCategory lit chaque champ du schema Category", () => {
    const fields = Object.keys(schemas.Category.properties);
    const read = keysReadBy(toCategory, Object.fromEntries(fields.map((f) => [f, "x"])));
    assert.deepEqual(fields.filter((f) => !read.has(f)), []);
  });

  it("le corps de l'inscription suit le schema UserCreate", () => {
    const fields = Object.keys(schemas.UserCreate.properties);
    assert.deepEqual(typedefProperties("userDto.js", "UserCreateDto"), fields);
    const body = toUserCreateDto({
      profile: "creator", firstName: "A", lastName: "B", email: "a@isep.fr", schoolId: "1", password: "Soiree2026",
      passwordConfirm: "Soiree2026", termsAccepted: true, interests: ["soiree"], searchRadiusKm: 3, organizationName: "BDE", individual: false, website: "",
    });
    assert.deepEqual(Object.keys(body), fields, "ni plus ni moins de champs que l'API (la confirmation ne part pas)");
  });

  it("les reponses de l'inscription et de la confirmation suivent leurs schemas", () => {
    /** @type {Array<[string, string, string, (dto: any) => unknown]>} */
    const cases = [
      ["userDto.js", "RegisteredUserDto", "RegisteredUser", toRegisteredUser],
      ["userDto.js", "EmailVerificationDto", "EmailVerification", toEmailVerification],
      ["schoolDto.js", "SchoolDto", "School", toSchool],
    ];
    for (const [file, name, schema, mapper] of cases) {
      const fields = Object.keys(schemas[schema].properties);
      assert.deepEqual(typedefProperties(file, name), fields, name);
      const read = keysReadBy(mapper, Object.fromEntries(fields.map((f) => [f, sample(schemas[schema].properties[f])])));
      assert.deepEqual(fields.filter((f) => !read.has(f)), [], `${name} : champs ignores par le mapper`);
    }
    assert.deepEqual(typedefProperties("userDto.js", "EmailVerificationCreateDto"), Object.keys(schemas.EmailVerificationCreate.properties));
  });

  it("toEventDto renvoie exactement les champs du schema Event", () => {
    const fields = Object.keys(schemas.Event.properties);
    const dto = Object.fromEntries(fields.map((f) => [f, sample(schemas.Event.properties[f])]));
    dto.categories = [];
    assert.deepEqual(Object.keys(toEventDto(toEvent(/** @type {any} */ (dto)))), fields);
  });
});
