import { apiClient } from "../core/api/axiosclient";

export interface Location {
  id: string;
  name: string;
  zip_code?: string;
}

export interface ShippingCost {
  service: string;
  description: string;
  cost: number;
  etd: string;
}

class ShippingRepository {
  async getProvinces(): Promise<Location[]> {
    const { data } = await apiClient.get("/shipping/provinces");
    return data;
  }

  async getCities(provinceId: string): Promise<Location[]> {
    const { data } = await apiClient.get("/shipping/cities", {
      params: { province_id: provinceId },
    });
    return data;
  }

  async getDistricts(cityId: string): Promise<Location[]> {
    const { data } = await apiClient.get("/shipping/districts", {
      params: { city_id: cityId },
    });
    return data;
  }

  async calculateCost(
    destinationDistrictId: string,
    weight: number,
    courier: string = "jne",
  ): Promise<any> {
    const { data } = await apiClient.post("/shipping/cost", {
      destination_district_id: destinationDistrictId,
      weight: weight,
      courier: courier,
    });
    return data;
  }
}

export const shippingRepository = new ShippingRepository();
