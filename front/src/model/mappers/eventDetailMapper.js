import { toEvent } from "./eventMapper.js";

/** @param {import("../dto/eventDetailDto.js").CreatorDto} dto */
export function toCreator(dto) {
  return {
    id: dto.id,
    displayName: dto.display_name,
    description: dto.description,
    avatarUrl: dto.avatar_url,
    schoolName: dto.school_name,
  };
}

/** @param {import("../dto/eventDetailDto.js").EventDetailDto} dto */
export function toEventDetail(dto) {
  return { ...toEvent(dto), creator: toCreator(dto.creator) };
}

/** Elements de GET /events : chacun porte son createur. */
export function toEventDetails(dtos) {
  return Array.isArray(dtos) ? dtos.map(toEventDetail) : [];
}
